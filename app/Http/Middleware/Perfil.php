<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restringe una ruta a ciertos perfiles: ->middleware('perfil:Administrador,Supervisor') */
class Perfil
{
    public function handle(Request $request, Closure $next, string ...$perfiles): Response
    {
        abort_unless($request->user()?->tienePerfil(...$perfiles), 403, 'Tu perfil no tiene acceso a esta sección.');

        return $next($request);
    }
}
