<?php

namespace App\Http\Middleware;

use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'hoy' => today()->toDateString(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'nombre_completo' => $user->nombre_completo,
                    'email' => $user->email,
                    'perfil' => $user->perfil,
                    'sucursales' => $user->esGlobal()
                        ? ['Todas las sucursales']
                        : $user->sucursales()->orderBy('nombre')->pluck('nombre'),
                ] : null,
                'can' => $user ? [
                    'programar' => $user->puedeProgramar(),
                    'admin' => $user->esAdmin(),
                    'global' => $user->esGlobal(),
                    'bitacora' => $user->tienePerfil(User::ADMIN, User::AUDITOR, User::SUPERVISOR),
                ] : [],
            ],
            'misSucursales' => fn () => $user ? \App\Support\SucursalActual::opciones($user) : [],
            'sucursalActual' => fn () => $request->session()->get('sucursal_actual'),
            'mensajesSinLeer' => fn () => $user && $user->tienePerfil(User::PROMOTOR, User::PROSPECTO)
                ? Mensaje::whereNull('leido_at')->where('from_id', '!=', $user->id)
                    ->where($user->perfil === User::PROSPECTO ? 'prospecto_user_id' : 'promotor_id', $user->id)->count()
                : 0,
            // Campana de notificaciones: visitas vencidas y mensajes sin leer
            'alertas' => fn () => $user && $user->perfil !== User::PROSPECTO ? [
                'vencidas' => \App\Models\Visita::visiblesPara($user)->where('estatus', \App\Models\Visita::PROGRAMADA)->whereDate('fecha', '<', today())->count(),
                'hoy' => \App\Models\Visita::visiblesPara($user)->whereIn('estatus', [\App\Models\Visita::PROGRAMADA, \App\Models\Visita::PROCESO])->whereDate('fecha', today())->count(),
                'porRevisar' => $user->tienePerfil(User::ADMIN, User::AUDITOR, User::SUPERVISOR)
                    ? \App\Models\Visita::visiblesPara($user)->where('estatus', \App\Models\Visita::REALIZADA)->count() : 0,
            ] : ['vencidas' => 0, 'hoy' => 0, 'porRevisar' => 0],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
