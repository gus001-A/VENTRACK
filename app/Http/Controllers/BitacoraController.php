<?php

namespace App\Http\Controllers;

use App\Models\SysLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Trazabilidad global: quién, cuándo (fecha y hora) y qué cambió en cualquier módulo. */
class BitacoraController extends Controller
{
    public const MODULOS = [
        'visitas' => 'Visitas', 'visita_observaciones' => 'Observaciones', 'visita_evidencias' => 'Evidencias',
        'prospectos' => 'Prospectos', 'sucursales' => 'Sucursales', 'users' => 'Usuarios', 'productos' => 'Productos',
        'catalogos' => 'Catálogos', 'documentos' => 'Documentos', 'mensajes' => 'Mensajes', 'sesiones' => 'Sesiones',
    ];

    public function index(Request $request): Response
    {
        $f = $request->only(['modulo', 'usuario', 'accion', 'desde', 'hasta', 'q']);

        $logs = SysLog::with('usuario:id,name,apellido_paterno,perfil')
            ->when($f['modulo'] ?? null, fn ($q, $v) => $q->where('tabla_afectada', $v))
            ->when($f['usuario'] ?? null, fn ($q, $v) => $q->where('usuario_id', $v))
            ->when($f['accion'] ?? null, fn ($q, $v) => $q->where('accion', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('descripcion', 'like', "%$v%"))
            ->latest('id')->paginate(25)->withQueryString()
            ->through(fn (SysLog $l) => $this->fila($l));

        return Inertia::render('Bitacora/Index', [
            'logs' => $logs,
            'filtros' => $f,
            'modulos' => self::MODULOS,
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'apellido_paterno'])->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_corto]),
        ]);
    }

    /** Bitácora de auditoría de un módulo (se consulta desde cada pantalla). */
    public function modulo(Request $request): JsonResponse
    {
        $tablas = [
            'visitas' => ['visitas', 'visita_observaciones', 'visita_evidencias'],
            'prospectos' => ['prospectos'], 'sucursales' => ['sucursales'], 'usuarios' => ['users'],
            'productos' => ['productos'], 'catalogos' => ['catalogos'], 'mensajes' => ['mensajes'],
        ];
        $data = $request->validate(['modulo' => ['required', 'in:'.implode(',', array_keys($tablas))], 'q' => ['nullable', 'string'], 'accion' => ['nullable', 'in:INSERT,UPDATE,DELETE']]);
        $user = $request->user();
        abort_if($user->tienePerfil(User::PROSPECTO), 403);

        $logs = SysLog::with('usuario:id,name,apellido_paterno,perfil')
            ->whereIn('tabla_afectada', $tablas[$data['modulo']])
            ->when($user->perfil === User::PROMOTOR, fn ($q) => $q->where('usuario_id', $user->id))
            ->when($data['accion'] ?? null, fn ($q, $v) => $q->where('accion', $v))
            ->when($data['q'] ?? null, fn ($q, $v) => $q->where('descripcion', 'like', "%$v%"))
            ->latest('id')->paginate(15);

        return response()->json([
            'data' => collect($logs->items())->map(fn ($l) => $this->fila($l)),
            'total' => $logs->total(),
            'current_page' => $logs->currentPage(),
            'per_page' => $logs->perPage(),
        ]);
    }

    /** Historial compacto de un registro específico (componente "Última modificación"). */
    public function registro(Request $request): JsonResponse
    {
        $data = $request->validate(['tabla' => ['required', 'string'], 'id' => ['required', 'integer']]);
        $user = $request->user();
        abort_if($user->tienePerfil(User::PROSPECTO), 403);

        $logs = SysLog::with('usuario:id,name,apellido_paterno,perfil')
            ->where('tabla_afectada', $data['tabla'])->where('registro_id', $data['id'])
            ->when($user->perfil === User::PROMOTOR, fn ($q) => $q->where('usuario_id', $user->id))
            ->latest('id')->limit(100)->get()->map(fn ($l) => $this->fila($l));

        return response()->json($logs);
    }

    private function fila(SysLog $l): array
    {
        return [
            'id' => $l->id,
            'usuario' => $l->usuario?->nombre_corto ?? 'Sistema',
            'perfil' => $l->usuario?->perfil,
            'fecha' => $l->fecha->toDateString(),
            'hora' => $l->hora,
            'modulo' => self::MODULOS[$l->tabla_afectada] ?? $l->tabla_afectada,
            'tabla' => $l->tabla_afectada,
            'registro_id' => $l->registro_id,
            'accion' => $l->accion,
            'descripcion' => $l->descripcion,
            'cambios' => $l->cambios_json,
            'ip' => $l->ip,
        ];
    }
}
