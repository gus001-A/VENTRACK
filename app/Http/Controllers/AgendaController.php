<?php

namespace App\Http\Controllers;

use App\Models\Visita;
use App\Support\Presenta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Agenda de visitas programadas: vista mensual, semanal y lista con alertas de incumplimiento. */
class AgendaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $vista = in_array($request->input('vista'), ['mes', 'semana', 'lista'], true) ? $request->input('vista') : 'mes';
        $ref = rescue(fn () => Carbon::parse($request->input('fecha', today()->toDateString())), today(), false);

        if ($vista === 'mes') {
            $desde = $ref->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $hasta = $desde->copy()->addDays(41);
        } elseif ($vista === 'semana') {
            $desde = $ref->copy()->startOfWeek(Carbon::MONDAY);
            $hasta = $desde->copy()->addDays(6);
        } else {
            $desde = null;
            $hasta = null;
        }

        $visitas = Visita::visiblesPara($user)->with(Presenta::conVisita())
            ->when($vista === 'lista',
                fn ($q) => $q->whereIn('estatus', [Visita::PROGRAMADA, Visita::PROCESO]),
                fn ($q) => $q->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]))
            ->orderBy('fecha')->orderBy('hora')->get();

        return Inertia::render('Agenda/Index', [
            'vista' => $vista,
            'fecha' => $ref->toDateString(),
            'desde' => $desde?->toDateString(),
            'visitas' => $visitas->map(fn ($v) => Presenta::visita($v)),
            'opciones' => Presenta::opcionesProgramar($user),
        ]);
    }
}
