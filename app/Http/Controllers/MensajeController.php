<?php

namespace App\Http\Controllers;

use App\Models\Mensaje;
use App\Models\User;
use App\Models\Visita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** Portal de prospectos: comunicación directa prospecto ⇄ promotor asignado. */
class MensajeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $contactos = $this->contactos($user);
        $chat = $request->integer('con') ?: $contactos->first()?->id;
        if (! $contactos->contains('id', $chat)) {
            $chat = $contactos->first()?->id;
        }

        $mensajes = collect();
        if ($chat) {
            [$pr, $pm] = $user->perfil === User::PROSPECTO ? [$user->id, $chat] : [$chat, $user->id];
            $q = Mensaje::where('prospecto_user_id', $pr)->where('promotor_id', $pm);
            (clone $q)->whereNull('leido_at')->where('from_id', '!=', $user->id)->update(['leido_at' => now()]);
            $mensajes = $q->oldest()->get()->map(fn ($m) => [
                'id' => $m->id, 'texto' => $m->texto, 'mio' => $m->from_id === $user->id,
                'fecha' => $m->created_at->format('Y-m-d H:i'), 'leido' => (bool) $m->leido_at,
            ]);
        }

        $campo = $user->perfil === User::PROSPECTO ? 'promotor_id' : 'prospecto_user_id';
        $propio = $user->perfil === User::PROSPECTO ? 'prospecto_user_id' : 'promotor_id';
        $sinLeer = Mensaje::where($propio, $user->id)->whereNull('leido_at')->where('from_id', '!=', $user->id)
            ->selectRaw("$campo as c, count(*) as n")->groupBy($campo)->pluck('n', 'c');

        return Inertia::render('Mensajes/Index', [
            'contactos' => $contactos->map(fn ($u) => [
                'id' => $u->id, 'nombre' => $u->nombre_completo, 'telefono' => $u->telefono, 'sinLeer' => (int) ($sinLeer[$u->id] ?? 0),
            ])->values(),
            'chat' => $chat,
            'mensajes' => $mensajes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['con' => ['required', 'integer'], 'texto' => ['required', 'string', 'max:2000']]);
        abort_unless($this->contactos($user)->contains('id', $data['con']), 403, 'No puedes escribir a este usuario.');

        Mensaje::create([
            'prospecto_user_id' => $user->perfil === User::PROSPECTO ? $user->id : $data['con'],
            'promotor_id' => $user->perfil === User::PROSPECTO ? $data['con'] : $user->id,
            'from_id' => $user->id,
            'texto' => $data['texto'],
        ]);

        return back();
    }

    /** Prospecto: promotores con visitas a su establecimiento. Promotor: prospectos con portal a los que visita. */
    private function contactos(User $user): Collection
    {
        if ($user->perfil === User::PROSPECTO) {
            $ids = Visita::visiblesPara($user)->distinct()->pluck('promotor_id');

            return User::whereIn('id', $ids)->activos()->orderBy('name')->get();
        }

        $ids = Visita::where('promotor_id', $user->id)->join('prospectos', 'prospectos.id', '=', 'visitas.prospecto_id')
            ->whereNotNull('prospectos.user_id')->distinct()->pluck('prospectos.user_id')
            ->merge(Mensaje::where('promotor_id', $user->id)->distinct()->pluck('prospecto_user_id'))->unique();

        return User::whereIn('id', $ids)->activos()->orderBy('name')->get();
    }
}
