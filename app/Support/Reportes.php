<?php

namespace App\Support;

use App\Models\Prospecto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Reportes de VENTRACK (vista previa, PDF y Excel comparten los mismos datos).
 * Cada reporte devuelve: titulo, descripcion, columnas, filas y resumen.
 */
class Reportes
{
    public const TIPOS = [
        'visitas' => ['titulo' => 'Reporte de visitas', 'descripcion' => 'Detalle de visitas con estatus, resultado y comprobación de ubicación.'],
        'promotores' => ['titulo' => 'Desempeño por promotor', 'descripcion' => 'Visitas programadas, realizadas, ventas, vencidas y efectividad por promotor.'],
        'prospectos' => ['titulo' => 'Reporte de prospectos', 'descripcion' => 'Prospectos de la sucursal con su historial de visitas y ventas.'],
    ];

    private const REALIZADAS = [Visita::REALIZADA, Visita::REVISADA, Visita::SUPERVISADA];

    public function __construct(private User $user, private ?int $sucursal, private array $f) {}

    public function generar(string $tipo): array
    {
        $datos = match ($tipo) {
            'visitas' => $this->visitas(),
            'promotores' => $this->promotores(),
            'prospectos' => $this->prospectos(),
        };

        return [
            ...self::TIPOS[$tipo],
            'tipo' => $tipo,
            'sucursal' => $this->sucursal ? Sucursal::whereKey($this->sucursal)->value('nombre') : 'Todas',
            'periodo' => $this->periodo(),
            'generado_por' => $this->user->nombre_completo,
            'generado_en' => now()->format('d/m/Y h:i a'),
            ...$datos,
        ];
    }

    /** Consulta base de visitas con los mismos filtros de la lista de trabajo. */
    public function consultaVisitas(): Builder
    {
        $f = $this->f;

        return Visita::visiblesPara($this->user)
            ->when($this->sucursal, fn ($q, $v) => $q->where('sucursal_id', $v))
            ->when($f['promotor'] ?? null, fn ($q, $v) => $q->where('promotor_id', $v))
            ->when($f['estatus'] ?? null, fn ($q, $v) => $q->where('estatus', $v))
            ->when($f['resultado'] ?? null, fn ($q, $v) => $v === 'sin' ? $q->whereNull('resultado') : $q->where('resultado', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($f['vencidas'] ?? null, fn ($q) => $q->where('estatus', Visita::PROGRAMADA)->whereDate('fecha', '<', today()))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->whereHas('prospecto', fn ($p) => $p->where('nombre', 'like', "%{$v}%")));
    }

    private function visitas(): array
    {
        $visitas = $this->consultaVisitas()
            ->with(['prospecto:id,nombre,tipo_negocio_id', 'prospecto.tipoNegocio:id,nombre', 'promotor:id,name,apellido_paterno'])
            ->withCount('observaciones')
            ->orderBy('fecha')->orderBy('hora')->get();

        $filas = $visitas->map(fn (Visita $v) => [
            'fecha' => $v->fecha->format('d/m/Y'),
            'hora' => $v->hora ? Carbon::parse($v->hora)->format('h:i a') : '—',
            'prospecto' => $v->prospecto?->nombre,
            'giro' => $v->prospecto?->tipoNegocio?->nombre ?? '—',
            'promotor' => $v->promotor?->nombre_corto,
            'estatus' => Visita::ETIQUETAS[$v->estatus] ?? $v->estatus,
            'resultado' => match ($v->resultado) {
                'positiva' => 'Con venta',
                'negativa' => 'Sin venta',
                default => $v->vencida ? 'Vencida' : 'Sin resultado',
            },
            'ubicacion' => $v->latitud !== null ? 'Comprobada' : 'Pendiente',
            'observaciones' => $v->observaciones_count,
        ])->values()->all();

        $realizadas = $visitas->whereIn('estatus', self::REALIZADAS);
        $positivas = $visitas->where('resultado', 'positiva')->count();

        return [
            'columnas' => [
                ['key' => 'fecha', 'label' => 'Fecha', 'ancho' => 12],
                ['key' => 'hora', 'label' => 'Hora', 'ancho' => 10],
                ['key' => 'prospecto', 'label' => 'Prospecto', 'ancho' => 30],
                ['key' => 'giro', 'label' => 'Giro', 'ancho' => 16],
                ['key' => 'promotor', 'label' => 'Promotor', 'ancho' => 20],
                ['key' => 'estatus', 'label' => 'Estatus', 'ancho' => 14],
                ['key' => 'resultado', 'label' => 'Resultado', 'ancho' => 14],
                ['key' => 'ubicacion', 'label' => 'Ubicación', 'ancho' => 13],
                ['key' => 'observaciones', 'label' => 'Observ.', 'ancho' => 9, 'align' => 'center'],
            ],
            'filas' => $filas,
            'resumen' => [
                ['label' => 'Visitas', 'valor' => $visitas->count(), 'color' => '#0461d8'],
                ['label' => 'Realizadas', 'valor' => $realizadas->count(), 'color' => '#16a34a'],
                ['label' => 'Con venta', 'valor' => $positivas, 'color' => '#1faf5c'],
                ['label' => 'Sin venta', 'valor' => $visitas->where('resultado', 'negativa')->count(), 'color' => '#e5484d'],
                ['label' => 'Vencidas', 'valor' => $visitas->filter->vencida->count(), 'color' => '#d97706'],
                ['label' => 'Efectividad', 'valor' => $this->porcentaje($positivas, $realizadas->count()), 'color' => '#1597a5'],
            ],
        ];
    }

    private function promotores(): array
    {
        $visitas = $this->consultaVisitas()->with('promotor:id,name,apellido_paterno')->get();

        $filas = $visitas->groupBy('promotor_id')->map(function ($grupo) {
            $realizadas = $grupo->whereIn('estatus', self::REALIZADAS)->count();
            $positivas = $grupo->where('resultado', 'positiva')->count();

            return [
                'promotor' => $grupo->first()->promotor?->nombre_corto ?? 'Sin promotor',
                'programadas' => $grupo->count(),
                'realizadas' => $realizadas,
                'positivas' => $positivas,
                'negativas' => $grupo->where('resultado', 'negativa')->count(),
                'pendientes' => $grupo->whereIn('estatus', [Visita::PROGRAMADA, Visita::PROCESO])->count(),
                'vencidas' => $grupo->filter->vencida->count(),
                'cumplimiento' => $this->porcentaje($realizadas, $grupo->count()),
                'efectividad' => $this->porcentaje($positivas, $realizadas),
            ];
        })->sortByDesc('positivas')->values()->all();

        $realizadas = $visitas->whereIn('estatus', self::REALIZADAS)->count();
        $positivas = $visitas->where('resultado', 'positiva')->count();

        return [
            'columnas' => [
                ['key' => 'promotor', 'label' => 'Promotor', 'ancho' => 28],
                ['key' => 'programadas', 'label' => 'Programadas', 'ancho' => 13, 'align' => 'center'],
                ['key' => 'realizadas', 'label' => 'Realizadas', 'ancho' => 12, 'align' => 'center'],
                ['key' => 'positivas', 'label' => 'Con venta', 'ancho' => 12, 'align' => 'center'],
                ['key' => 'negativas', 'label' => 'Sin venta', 'ancho' => 12, 'align' => 'center'],
                ['key' => 'pendientes', 'label' => 'Pendientes', 'ancho' => 12, 'align' => 'center'],
                ['key' => 'vencidas', 'label' => 'Vencidas', 'ancho' => 11, 'align' => 'center'],
                ['key' => 'cumplimiento', 'label' => 'Cumplimiento', 'ancho' => 14, 'align' => 'center'],
                ['key' => 'efectividad', 'label' => 'Efectividad', 'ancho' => 13, 'align' => 'center'],
            ],
            'filas' => $filas,
            'resumen' => [
                ['label' => 'Promotores', 'valor' => count($filas), 'color' => '#3f51b5'],
                ['label' => 'Visitas', 'valor' => $visitas->count(), 'color' => '#0461d8'],
                ['label' => 'Realizadas', 'valor' => $realizadas, 'color' => '#16a34a'],
                ['label' => 'Con venta', 'valor' => $positivas, 'color' => '#1faf5c'],
                ['label' => 'Cumplimiento', 'valor' => $this->porcentaje($realizadas, $visitas->count()), 'color' => '#7c4ddb'],
                ['label' => 'Efectividad', 'valor' => $this->porcentaje($positivas, $realizadas), 'color' => '#1597a5'],
            ],
        ];
    }

    private function prospectos(): array
    {
        $f = $this->f;
        $prospectos = Prospecto::visiblesPara($this->user)
            ->when($this->sucursal, fn ($q, $v) => $q->where('sucursal_id', $v))
            ->with('tipoNegocio:id,nombre')
            ->withCount([
                'visitas' => fn ($q) => $q->when($f['desde'] ?? null, fn ($w, $v) => $w->whereDate('fecha', '>=', $v))
                    ->when($f['hasta'] ?? null, fn ($w, $v) => $w->whereDate('fecha', '<=', $v)),
                'visitas as positivas_count' => fn ($q) => $q->where('resultado', 'positiva')
                    ->when($f['desde'] ?? null, fn ($w, $v) => $w->whereDate('fecha', '>=', $v))
                    ->when($f['hasta'] ?? null, fn ($w, $v) => $w->whereDate('fecha', '<=', $v)),
            ])
            ->withMax('visitas', 'fecha')
            ->orderBy('nombre')->get();

        $filas = $prospectos->map(fn (Prospecto $p) => [
            'prospecto' => $p->nombre,
            'persona' => $p->tipo_persona,
            'giro' => $p->tipoNegocio?->nombre ?? '—',
            'contacto' => $p->contacto ?: '—',
            'telefono' => $p->telefono ?: '—',
            'ciudad' => $p->ciudad ?: '—',
            'visitas' => $p->visitas_count,
            'ventas' => $p->positivas_count,
            'ultima' => $p->visitas_max_fecha ? Carbon::parse($p->visitas_max_fecha)->format('d/m/Y') : '—',
            'estado' => $p->activo ? 'Activo' : 'Inactivo',
        ])->values()->all();

        return [
            'columnas' => [
                ['key' => 'prospecto', 'label' => 'Prospecto', 'ancho' => 30],
                ['key' => 'persona', 'label' => 'Persona', 'ancho' => 10],
                ['key' => 'giro', 'label' => 'Giro', 'ancho' => 16],
                ['key' => 'contacto', 'label' => 'Contacto', 'ancho' => 22],
                ['key' => 'telefono', 'label' => 'Teléfono', 'ancho' => 14],
                ['key' => 'ciudad', 'label' => 'Ciudad', 'ancho' => 16],
                ['key' => 'visitas', 'label' => 'Visitas', 'ancho' => 9, 'align' => 'center'],
                ['key' => 'ventas', 'label' => 'Ventas', 'ancho' => 9, 'align' => 'center'],
                ['key' => 'ultima', 'label' => 'Última visita', 'ancho' => 13],
                ['key' => 'estado', 'label' => 'Estado', 'ancho' => 10],
            ],
            'filas' => $filas,
            'resumen' => [
                ['label' => 'Prospectos', 'valor' => $prospectos->count(), 'color' => '#f08c00'],
                ['label' => 'Activos', 'valor' => $prospectos->where('activo', true)->count(), 'color' => '#16a34a'],
                ['label' => 'Con visitas', 'valor' => $prospectos->where('visitas_count', '>', 0)->count(), 'color' => '#7c4ddb'],
                ['label' => 'Con venta', 'valor' => $prospectos->where('positivas_count', '>', 0)->count(), 'color' => '#1faf5c'],
                ['label' => 'Sin visitar', 'valor' => $prospectos->where('visitas_count', 0)->count(), 'color' => '#e5484d'],
            ],
        ];
    }

    private function periodo(): string
    {
        $d = $this->f['desde'] ?? null;
        $h = $this->f['hasta'] ?? null;
        $fmt = fn ($x) => Carbon::parse($x)->format('d/m/Y');

        return match (true) {
            $d && $h => $fmt($d).' al '.$fmt($h),
            (bool) $d => 'Desde '.$fmt($d),
            (bool) $h => 'Hasta '.$fmt($h),
            default => 'Todo el historial',
        };
    }

    private function porcentaje(int $parte, int $total): string
    {
        return $total ? round($parte * 100 / $total).'%' : '0%';
    }
}
