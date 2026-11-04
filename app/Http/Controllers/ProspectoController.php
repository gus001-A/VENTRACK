<?php

namespace App\Http\Controllers;

use App\Models\Catalogo;
use App\Models\Prospecto;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Presenta;
use App\Support\SucursalActual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProspectoController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $f = $request->only(['q', 'tipo_negocio', 'tipo_persona', 'ver', 'ubicacion']);
        $sucursal = SucursalActual::resolver($request);

        $prospectos = Prospecto::visiblesPara($user)
            ->when($sucursal, fn ($q, $v) => $q->where('sucursal_id', $v))
            ->with(['sucursal:id,nombre', 'tipoNegocio:id,nombre', 'usuario:id,email,activo'])
            ->withCount(['visitas', 'documentos', 'visitas as positivas_count' => fn ($q) => $q->where('resultado', 'positiva')])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('nombre', 'like', "%$v%")->orWhere('nombre_comercial', 'like', "%$v%")->orWhere('contacto', 'like', "%$v%")))
            ->when($f['tipo_negocio'] ?? null, fn ($q, $v) => $q->where('tipo_negocio_id', $v))
            ->when($f['tipo_persona'] ?? null, fn ($q, $v) => $q->where('tipo_persona', $v))
            ->where('activo', ($f['ver'] ?? null) !== 'inactivos')
            ->when($f['ubicacion'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('direccion', 'like', "%$v%")
                ->orWhere('colonia', 'like', "%$v%")->orWhere('ciudad', 'like', "%$v%")->orWhere('codigo_postal', 'like', "%$v%")))
            ->orderBy('nombre')->paginate(20)->withQueryString()
            ->through(fn (Prospecto $p) => [
                ...$p->only(['id', 'tipo_persona', 'nombre', 'nombre_comercial', 'rfc', 'contacto', 'telefono', 'email', 'direccion',
                    'colonia', 'ciudad', 'estado', 'codigo_postal', 'latitud', 'longitud', 'tipo_negocio_id', 'sucursal_id', 'notas', 'activo']),
                'sucursal' => $p->sucursal?->nombre,
                'giro' => $p->tipoNegocio?->nombre,
                'maps' => $p->maps_url,
                'portal' => $p->usuario ? ['email' => $p->usuario->email, 'activo' => $p->usuario->activo] : null,
                'visitas_count' => $p->visitas_count,
                'positivas_count' => $p->positivas_count,
                'documentos_count' => $p->documentos_count,
                'traza' => Presenta::traza('prospectos', $p->id),
            ]);

        $base = Prospecto::visiblesPara($user)->when($sucursal, fn ($q, $v) => $q->where('sucursal_id', $v));

        return Inertia::render('Prospectos/Index', [
            'prospectos' => $prospectos,
            'kpis' => [
                'total' => (clone $base)->count(),
                'activos' => (clone $base)->where('activo', true)->count(),
                'inactivos' => (clone $base)->where('activo', false)->count(),
                'portal' => (clone $base)->whereNotNull('user_id')->count(),
                'visitados' => (clone $base)->has('visitas')->count(),
                'clientes' => (clone $base)->whereHas('visitas', fn ($q) => $q->where('resultado', 'positiva'))->count(),
            ],
            'filtros' => $f,
            'sucursales' => Sucursal::whereIn('id', $user->sucursalIds())->orderBy('nombre')->get(['id', 'nombre']),
            'tiposNegocio' => Catalogo::opciones(Catalogo::TIPO_NEGOCIO),
            'puedeEditar' => $user->puedeProgramar(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        DB::transaction(function () use ($data, $request) {
            $p = Prospecto::create(collect($data)->except(['portal', 'portal_email', 'portal_password'])->all());
            $this->sincronizarPortal($p, $data, $request->user());
        });

        return back()->with('success', 'Prospecto registrado');
    }

    public function update(Request $request, Prospecto $prospecto): RedirectResponse
    {
        $this->autorizar($request->user(), $prospecto);
        $data = $this->validar($request, $prospecto);
        DB::transaction(function () use ($data, $prospecto, $request) {
            $prospecto->update(collect($data)->except(['portal', 'portal_email', 'portal_password'])->all());
            $this->sincronizarPortal($prospecto, $data, $request->user());
        });

        return back()->with('success', 'Prospecto actualizado');
    }

    /** Activa o desactiva el prospecto (y su acceso al portal). */
    public function destroy(Request $request, Prospecto $prospecto): RedirectResponse
    {
        $this->autorizar($request->user(), $prospecto);
        $prospecto->update(['activo' => ! $prospecto->activo]);
        if (! $prospecto->activo) {
            $prospecto->usuario?->update(['activo' => false]);
        }

        return back()->with('success', $prospecto->activo ? 'Prospecto activado' : 'Prospecto desactivado');
    }

    private function validar(Request $request, ?Prospecto $p = null): array
    {
        $user = $request->user();

        return $request->validate([
            'tipo_persona' => ['required', Rule::in(['Física', 'Moral'])],
            'nombre' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'rfc' => ['nullable', 'string', 'max:13'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'regex:/^\d{10,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['required', 'string', 'max:255'],
            'colonia' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'tipo_negocio_id' => ['nullable', Rule::exists('catalogos', 'id')->where('tipo', Catalogo::TIPO_NEGOCIO)],
            'sucursal_id' => ['required', Rule::in($user->sucursalIds())],
            'notas' => ['nullable', 'string', 'max:5000'],
            'portal' => ['boolean'],
            'portal_email' => ['nullable', 'required_if:portal,true', 'email', Rule::unique('users', 'email')->ignore($p?->user_id)],
            'portal_password' => [$p?->user_id ? 'nullable' : 'required_if:portal,true', 'nullable', 'string', 'min:8'],
        ], ['telefono.regex' => 'El teléfono solo debe contener números (10 a 15 dígitos).'], ['portal_email' => 'correo de acceso', 'portal_password' => 'contraseña', 'sucursal_id' => 'sucursal']);
    }

    /** Alta/baja del prospecto como usuario del portal para contactar a sus promotores. */
    private function sincronizarPortal(Prospecto $p, array $data, User $actor): void
    {
        if (! ($data['portal'] ?? false)) {
            if ($p->usuario && $p->usuario->activo) {
                $p->usuario->update(['activo' => false]);
            }

            return;
        }

        $attrs = ['name' => $p->nombre, 'email' => $data['portal_email'], 'telefono' => $p->telefono, 'perfil' => User::PROSPECTO, 'activo' => true];
        if (filled($data['portal_password'] ?? null)) {
            $attrs['password'] = $data['portal_password'];
        }

        if ($p->usuario) {
            $p->usuario->update($attrs);
            $u = $p->usuario;
        } else {
            $u = User::create($attrs);
            $p->update(['user_id' => $u->id]);
        }
        $u->sucursales()->sync([$p->sucursal_id]);
    }

    private function autorizar(User $user, Prospecto $p): void
    {
        abort_unless(in_array($p->sucursal_id, $user->sucursalIds()), 403);
    }
}
