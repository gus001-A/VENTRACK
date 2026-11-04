<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use App\Models\User;
use App\Models\Visita;
use App\Support\Presenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $hoy = today();

        if ($user->perfil === User::PROSPECTO) {
            $visitas = Visita::visiblesPara($user)->with(Presenta::conVisita())->orderByDesc('fecha')->limit(20)->get();

            return Inertia::render('Panel/Prospecto', [
                'visitas' => $visitas->map(fn ($v) => Presenta::visita($v)),
                'promotores' => User::whereIn('id', $visitas->pluck('promotor_id')->unique())->get()
                    ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_completo, 'telefono' => $u->telefono, 'email' => $u->email]),
            ]);
        }

        $misSucursales = Sucursal::whereIn('id', $user->sucursalIds())->orderBy('nombre')->get(['id', 'nombre']);
        $sucursal = $request->integer('sucursal') ?: null;
        if ($sucursal && ! $misSucursales->contains('id', $sucursal)) {
            $sucursal = null;
        }

        $base = fn () => Visita::visiblesPara($user)->when($sucursal, fn ($q) => $q->where('sucursal_id', $sucursal));

        $porEstatus = $base()->select('estatus', DB::raw('count(*) as total'))->groupBy('estatus')->pluck('total', 'estatus');
        $porResultado = $base()->whereNotNull('resultado')->select('resultado', DB::raw('count(*) as total'))->groupBy('resultado')->pluck('total', 'resultado');
        $total = $porEstatus->sum();

        $deHoy = $base()->whereDate('fecha', $hoy)->with(Presenta::conVisita())->orderBy('hora')->get();
        // Agenda unificada: hoy, mañana y los próximos 7 días (con detalle)
        $agenda = $base()->whereBetween('fecha', [$hoy->toDateString(), $hoy->copy()->addDays(6)->toDateString()])
            ->with(Presenta::conVisita())->orderBy('fecha')->orderBy('hora')->get();

        // Visitas por tipo de negocio del prospecto
        $giros = $base()->with('prospecto:id,tipo_negocio_id', 'prospecto.tipoNegocio:id,nombre')->get(['id', 'prospecto_id', 'resultado'])
            ->groupBy(fn ($v) => $v->prospecto?->tipoNegocio?->nombre ?? 'Sin giro')
            ->map(fn ($l, $nombre) => ['nombre' => $nombre, 'total' => $l->count(), 'positivas' => $l->where('resultado', 'positiva')->count()])
            ->sortByDesc('total')->values()->take(6);

        return Inertia::render('Panel/Index', [
            'sucursales' => $misSucursales,
            'sucursal' => $sucursal,
            'kpis' => [
                'hoy' => $deHoy->count(),
                'hoyPorIniciar' => $deHoy->where('estatus', Visita::PROGRAMADA)->count(),
                'proceso' => (int) ($porEstatus[Visita::PROCESO] ?? 0),
                'porRevisar' => (int) ($porEstatus[Visita::REALIZADA] ?? 0),
                'positivas' => (int) ($porResultado['positiva'] ?? 0),
                'negativas' => (int) ($porResultado['negativa'] ?? 0),
                'total' => $total,
            ],
            'porEstatus' => collect(Visita::ESTATUS)->mapWithKeys(fn ($s) => [$s => (int) ($porEstatus[$s] ?? 0)]),
            'deHoy' => $deHoy->map(fn ($v) => Presenta::visita($v)),
            'vencidasTotal' => $base()->where('estatus', Visita::PROGRAMADA)->whereDate('fecha', '<', $hoy)->count(),
            'agenda' => $agenda->map(fn ($v) => Presenta::visita($v)),
            'giros' => $giros,
            'opciones' => Presenta::opcionesProgramar($user),
        ]);
    }
}
