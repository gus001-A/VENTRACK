<?php

namespace App\Http\Controllers;

use App\Models\Prospecto;
use App\Models\User;
use App\Models\Visita;
use App\Models\VisitaEvidencia;
use App\Models\VisitaObservacion;
use App\Support\Presenta;
use App\Support\SucursalActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitaController extends Controller
{
    /** Lista de trabajo filtrada automáticamente por perfil y sucursales asignadas. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $request->validate([
            'desde' => ['nullable', 'date', 'before_or_equal:today'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ], ['desde.before_or_equal' => 'La fecha "desde" no puede ser mayor a hoy.', 'hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".']);
        $f = $request->only(['estatus', 'resultado', 'promotor', 'desde', 'hasta', 'q', 'vencidas']);
        $sucursal = SucursalActual::resolver($request);

        $base = Visita::visiblesPara($user)
            ->when($sucursal, fn ($q, $v) => $q->where('sucursal_id', $v))
            ->when($f['promotor'] ?? null, fn ($q, $v) => $q->where('promotor_id', $v))
            ->when($f['resultado'] ?? null, fn ($q, $v) => $v === 'sin' ? $q->whereNull('resultado') : $q->where('resultado', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($f['vencidas'] ?? null, fn ($q) => $q->where('estatus', Visita::PROGRAMADA)->whereDate('fecha', '<', today()))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->whereHas('prospecto', fn ($p) => $p->where('nombre', 'like', "%{$v}%")));

        $conteos = (clone $base)->select('estatus', DB::raw('count(*) as total'))->groupBy('estatus')->pluck('total', 'estatus');

        $visitas = (clone $base)
            ->when($f['estatus'] ?? null, fn ($q, $v) => $q->where('estatus', $v))
            ->with(Presenta::conVisita())
            ->orderByDesc('fecha')->orderBy('hora')
            ->paginate(20)->withQueryString();
        $trazas = \App\Models\SysLog::trazasDe('visitas', $visitas->pluck('id'));
        $visitas->through(fn ($v) => [...Presenta::visita($v), 'traza' => $trazas[$v->id] ?? null]);

        return Inertia::render('Visitas/Index', [
            'visitas' => $visitas,
            'conteos' => $conteos,
            'vencidas' => (clone $base)->where('estatus', Visita::PROGRAMADA)->whereDate('fecha', '<', today())->count(),
            'filtros' => $f,
            'promotores' => $user->perfil === User::PROMOTOR || $user->perfil === User::PROSPECTO
                ? [] : User::perfil(User::PROMOTOR)->when($sucursal, fn ($q, $v) => $q->whereHas('sucursales', fn ($s) => $s->where('sucursales.id', $v)))
                    ->orderBy('name')->get(['id', 'name', 'apellido_paterno'])
                    ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_corto]),
            'opciones' => Presenta::opcionesProgramar($user),
            'abrir' => $request->integer('ver') ?: null,
        ]);
    }

    /** Detalle para el panel lateral (JSON). */
    public function show(Request $request, Visita $visita): JsonResponse
    {
        $user = $request->user();
        $this->autorizarVer($user, $visita);
        $visita->load(['prospecto.tipoNegocio:id,nombre', 'sucursal:id,nombre', 'promotor:id,name,apellido_paterno', 'evidencias.usuario:id,name,apellido_paterno', 'programador:id,name,apellido_paterno']);

        $p = $visita->prospecto;

        return response()->json([
            ...Presenta::visita($visita),
            'prospecto' => [
                'id' => $p->id, 'nombre' => $p->nombre, 'direccion' => trim($p->direccion.', '.$p->colonia, ', '),
                'telefono' => $p->telefono, 'contacto' => $p->contacto, 'giro' => $p->tipoNegocio?->nombre,
                'maps' => $p->maps_url,
            ],
            'programada_por' => $visita->programador?->nombre_corto,
            'latitud' => $visita->latitud,
            'longitud' => $visita->longitud,
            'ubicacion_at' => $visita->ubicacion_at?->format('Y-m-d H:i'),
            'tiempos' => [
                'programada' => $visita->created_at?->format('Y-m-d H:i'),
                'proceso' => $visita->iniciada_at?->format('Y-m-d H:i'),
                'realizada' => $visita->realizada_at?->format('Y-m-d H:i'),
                'revisada' => $visita->revisada_at?->format('Y-m-d H:i'),
                'supervisada' => $visita->supervisada_at?->format('Y-m-d H:i'),
            ],
            'observaciones' => $visita->observacionesVisiblesPara($user)->map(fn ($o) => [
                'id' => $o->id,
                'texto' => $o->texto,
                'estatus' => $o->estatus,
                'formato' => $o->formato?->nombre,
                'adjuntos' => $o->adjuntos->map(fn ($e) => self::adjunto($e))->values(),
                'usuario' => $o->usuario?->nombre_corto,
                'perfil' => $o->usuario?->perfil,
                'fecha' => $o->created_at->format('Y-m-d H:i'),
            ]),
            'adjuntos' => $user->perfil === User::PROSPECTO ? [] : $visita->evidencias->whereIn('tipo', ['foto', 'documento'])->values()->map(fn ($e) => self::adjunto($e)),
            'ubicaciones' => $user->perfil === User::PROSPECTO ? [] : $visita->evidencias->where('tipo', 'ubicacion')->values()->map(fn ($e) => [
                'id' => $e->id, 'latitud' => $e->latitud, 'longitud' => $e->longitud, 'precision' => $e->precision,
                'estatus' => $e->estatus, 'usuario' => $e->usuario?->nombre_corto, 'fecha' => $e->created_at->format('Y-m-d H:i'),
            ]),
            'can' => [
                'siguiente' => $visita->siguientePara($user),
                'cerrar' => $visita->puedeCerrar($user),
                'evidencia' => $visita->puedeRegistrarEvidencia($user),
                'observar' => $user->perfil !== User::PROSPECTO && $visita->estatus !== Visita::SUPERVISADA,
                'editar' => $user->puedeProgramar() && $visita->estatus === Visita::PROGRAMADA,
            ],
            'formatos' => $user->perfil === User::PROSPECTO ? [] : Presenta::formatos(),
            'traza' => Presenta::traza('visitas', $visita->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'prospecto_id' => ['required', Rule::exists('prospectos', 'id')->whereIn('sucursal_id', $user->sucursalIds())],
            'promotor_id' => ['required', Rule::exists('users', 'id')->where('perfil', User::PROMOTOR)->where('activo', true)],
            'fecha' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'objetivo' => ['nullable', 'string', 'max:255'],
        ], [], ['prospecto_id' => 'prospecto', 'promotor_id' => 'promotor']);

        $prospecto = Prospecto::findOrFail($data['prospecto_id']);
        $promotor = User::findOrFail($data['promotor_id']);

        // El promotor queda asignado a la sucursal del prospecto si aún no lo estaba
        if (! $promotor->sucursales()->whereKey($prospecto->sucursal_id)->exists()) {
            $promotor->sucursales()->attach($prospecto->sucursal_id);
            $promotor->writeAudit('UPDATE', ['sucursales' => ['agregada' => $prospecto->sucursal->nombre]], $promotor->nombre_completo.' · asignado a '.$prospecto->sucursal->nombre);
        }

        $visita = new Visita([...$data, 'sucursal_id' => $prospecto->sucursal_id, 'programada_por' => $user->id, 'estatus' => Visita::PROGRAMADA]);
        $visita->setRelation('prospecto', $prospecto);
        $visita->auditNota = 'programada para '.$data['fecha'].' con '.$promotor->nombre_corto;
        $visita->save();

        return back()->with('success', 'Visita programada');
    }

    /** Editar o reprogramar (solo visitas programadas). */
    public function update(Request $request, Visita $visita): RedirectResponse
    {
        $this->autorizarVer($request->user(), $visita);
        abort_unless($visita->estatus === Visita::PROGRAMADA, 422, 'Solo se pueden reprogramar visitas programadas.');

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
            'promotor_id' => ['sometimes', Rule::exists('users', 'id')->where('perfil', User::PROMOTOR)->where('activo', true)],
            'objetivo' => ['nullable', 'string', 'max:255'],
        ]);
        $antes = $visita->fecha->toDateString();
        $visita->fill($data);
        if ($visita->isDirty('fecha')) {
            $visita->auditNota = 'reprogramada '.$antes.' → '.$data['fecha'];
        }
        $visita->save();

        return back()->with('success', 'Visita actualizada');
    }

    public function destroy(Request $request, Visita $visita): RedirectResponse
    {
        $this->autorizarVer($request->user(), $visita);
        abort_unless($visita->estatus === Visita::PROGRAMADA, 422, 'Solo se pueden cancelar visitas programadas.');
        $visita->load('prospecto:id,nombre');
        $visita->auditNota = 'cancelada';
        $visita->delete();

        return back()->with('success', 'Visita cancelada');
    }

    /** Avanza al siguiente estatus permitido (En proceso → requiere ubicación; Revisada; Supervisada). */
    public function avanzar(Request $request, Visita $visita): RedirectResponse
    {
        $user = $request->user();
        $this->autorizarVer($user, $visita);
        $this->noSupervisada($visita);
        $siguiente = $visita->siguientePara($user);
        abort_unless($siguiente, 403, 'No puedes cambiar el estatus de esta visita.');

        $data = $request->validate([
            'texto' => ['required', 'string', 'max:5000'],
            'formato_id' => ['nullable', 'exists:catalogos,id'],
            'archivos' => ['nullable', 'array', 'max:6'],
            'archivos.*' => ['file', 'mimes:'.self::MIMES, 'max:25600'],
            ...self::GEO,
        ], self::MENSAJES);

        if ($siguiente === Visita::PROCESO && ! isset($data['latitud'])) {
            return back()->with('error', 'Activa la ubicación del equipo para iniciar la visita.');
        }

        DB::transaction(function () use ($visita, $siguiente, $user, $data, $request) {
            $this->registrarUbicacion($visita, $user, $data);
            $visita->load('prospecto:id,nombre');
            $visita->estatus = $siguiente;
            $visita->{match ($siguiente) {
                Visita::PROCESO => 'iniciada_at',
                Visita::REVISADA => 'revisada_at',
                Visita::SUPERVISADA => 'supervisada_at',
            }} = now();
            $visita->auditNota = '→ '.Visita::ETIQUETAS[$siguiente];
            $visita->save();
            $obs = $this->guardarObservacion($visita, $user, $data);
            $this->guardarAdjuntos($visita, $user, $obs, $request->file('archivos', []));
        });

        return back()->with('success', 'Estatus actualizado a '.Visita::ETIQUETAS[$siguiente]);
    }

    /** Cierre de visita en campo: Positiva (venta/éxito) o Negativa. */
    public function cerrar(Request $request, Visita $visita): RedirectResponse
    {
        $user = $request->user();
        $this->autorizarVer($user, $visita);
        $this->noSupervisada($visita);
        abort_unless($visita->puedeCerrar($user), 403, 'No puedes cerrar esta visita.');

        $data = $request->validate([
            'resultado' => ['required', Rule::in(['positiva', 'negativa'])],
            'texto' => ['required', 'string', 'max:5000'],
            'formato_id' => ['nullable', 'exists:catalogos,id'],
            'archivos' => ['nullable', 'array', 'max:6'],
            'archivos.*' => ['file', 'mimes:'.self::MIMES, 'max:25600'],
            ...self::GEO,
        ], self::MENSAJES);

        if (! isset($data['latitud'])) {
            return back()->with('error', 'Activa la ubicación del equipo para cerrar la visita.');
        }

        DB::transaction(function () use ($visita, $user, $data, $request) {
            $this->registrarUbicacion($visita, $user, $data);
            $visita->load('prospecto:id,nombre');
            $visita->fill(['estatus' => Visita::REALIZADA, 'resultado' => $data['resultado'], 'realizada_at' => now()]);
            $visita->auditNota = '→ Realizada ('.ucfirst($data['resultado']).')';
            $visita->save();
            $obs = $this->guardarObservacion($visita, $user, $data);
            $this->guardarAdjuntos($visita, $user, $obs, $request->file('archivos', []));
        });

        return back()->with('success', 'Visita cerrada como '.$data['resultado']);
    }

    /** Geolocalización obligatoria al momento de la visita. */
    public function ubicacion(Request $request, Visita $visita): RedirectResponse
    {
        $user = $request->user();
        $this->autorizarVer($user, $visita);
        $this->noSupervisada($visita);
        abort_unless($visita->puedeRegistrarEvidencia($user), 403);

        $data = $request->validate([
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'precision' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->registrarUbicacion($visita, $user, $data);

        return back()->with('success', 'Ubicación guardada');
    }

    /** Fotografías de evidencia (sugeridas) desde el teléfono del promotor. */
    public function foto(Request $request, Visita $visita): RedirectResponse
    {
        $user = $request->user();
        $this->autorizarVer($user, $visita);
        $this->noSupervisada($visita);
        abort_unless($visita->puedeRegistrarEvidencia($user), 403);

        $request->validate(['foto' => ['required', 'image', 'max:12288']]);
        $path = $request->file('foto')->store('visitas/'.$visita->id, 'local');

        VisitaEvidencia::create(['visita_id' => $visita->id, 'user_id' => $user->id, 'estatus' => $visita->estatus, 'tipo' => 'foto', 'archivo' => $path]);

        return back()->with('success', 'Foto agregada');
    }

    public function observacion(Request $request, Visita $visita): RedirectResponse
    {
        $user = $request->user();
        $this->autorizarVer($user, $visita);
        $this->noSupervisada($visita);
        $data = $request->validate([
            'texto' => ['required', 'string', 'max:5000'],
            'formato_id' => ['nullable', 'exists:catalogos,id'],
            'archivos' => ['nullable', 'array', 'max:6'],
            'archivos.*' => ['file', 'mimes:'.self::MIMES, 'max:25600'],
            ...self::GEO,
        ], self::MENSAJES);
        DB::transaction(function () use ($visita, $user, $data, $request) {
            if ($visita->puedeRegistrarEvidencia($user)) {
                $this->registrarUbicacion($visita, $user, $data);
            }
            $obs = $this->guardarObservacion($visita, $user, $data);
            $this->guardarAdjuntos($visita, $user, $obs, $request->file('archivos', []));
        });

        return back()->with('success', 'Observación guardada');
    }

    public function archivo(Request $request, VisitaEvidencia $evidencia): StreamedResponse
    {
        $user = $request->user();
        abort_if($user->perfil === User::PROSPECTO, 403);
        $this->autorizarVer($user, Visita::findOrFail($evidencia->visita_id));
        abort_unless($evidencia->archivo && Storage::disk('local')->exists($evidencia->archivo), 404);

        return Storage::disk('local')->response($evidencia->archivo);
    }

    private function guardarObservacion(Visita $visita, User $user, array $data): ?VisitaObservacion
    {
        if (! filled($data['texto'] ?? null)) {
            return null;
        }

        return VisitaObservacion::create([
            'visita_id' => $visita->id,
            'user_id' => $user->id,
            'estatus' => $visita->estatus,
            'formato_id' => $data['formato_id'] ?? null,
            'texto' => $data['texto'],
        ]);
    }

    private const MENSAJES = [
        'texto.required' => 'Escribe un comentario para continuar con el proceso.',
        'archivos.max' => 'Puedes adjuntar hasta 6 archivos a la vez.',
        'archivos.*.mimes' => 'Solo se aceptan fotos, PDF y documentos de Office (Word, Excel, PowerPoint).',
        'archivos.*.max' => 'Cada archivo debe pesar máximo 25 MB.',
    ];

    /** Formatos de evidencia permitidos: imágenes, PDF y Office. */
    private const MIMES = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx';

    /** Fotos o documentos opcionales que acompañan un comentario o un cambio de estatus. */
    private function guardarAdjuntos(Visita $visita, User $user, ?VisitaObservacion $obs, array $archivos): void
    {
        foreach ($archivos as $archivo) {
            VisitaEvidencia::create([
                'visita_id' => $visita->id, 'user_id' => $user->id, 'visita_observacion_id' => $obs?->id, 'estatus' => $visita->estatus,
                'tipo' => str_starts_with((string) $archivo->getMimeType(), 'image/') ? 'foto' : 'documento',
                'archivo' => $archivo->store('visitas/'.$visita->id, 'local'),
                'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            ]);
        }
    }

    private static function adjunto(VisitaEvidencia $e): array
    {
        return [
            'id' => $e->id,
            'tipo' => $e->tipo,
            'nombre' => $e->nombre ?: 'Foto '.$e->id.'.'.pathinfo((string) $e->archivo, PATHINFO_EXTENSION),
            'url' => route('evidencias.archivo', $e),
            'estatus' => $e->estatus,
            'usuario' => $e->usuario?->nombre_corto,
            'fecha' => $e->created_at->format('Y-m-d H:i'),
        ];
    }

    /** Ubicación en tiempo real del equipo (GPS del navegador); en campo también comprueba la visita. */
    private function registrarUbicacion(Visita $visita, User $user, array $data): void
    {
        if (! isset($data['latitud'], $data['longitud'])) {
            return;
        }
        VisitaEvidencia::create([
            'visita_id' => $visita->id, 'user_id' => $user->id, 'estatus' => $visita->estatus, 'tipo' => 'ubicacion',
            'latitud' => $data['latitud'], 'longitud' => $data['longitud'], 'precision' => $data['precision'] ?? null,
        ]);
        if (in_array($visita->estatus, [Visita::PROGRAMADA, Visita::PROCESO], true)) {
            $visita->load('prospecto:id,nombre');
            $visita->fill(['latitud' => $data['latitud'], 'longitud' => $data['longitud'], 'ubicacion_at' => now()]);
            $visita->auditNota = 'ubicación capturada';
            $visita->save();
        }
    }

    private const GEO = [
        'latitud' => ['nullable', 'numeric', 'between:-90,90'],
        'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
        'precision' => ['nullable', 'numeric', 'min:0'],
    ];

    /** Una visita supervisada ya no admite cambios. */
    private function noSupervisada(Visita $visita): void
    {
        abort_if($visita->estatus === Visita::SUPERVISADA, 422, 'La visita ya fue supervisada; no se puede modificar.');
    }

    private function autorizarVer(User $user, Visita $visita): void
    {
        abort_unless(Visita::visiblesPara($user)->whereKey($visita->id)->exists(), 403, 'No tienes acceso a esta visita.');
    }
}
