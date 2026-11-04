<?php

namespace App\Http\Controllers;

use App\Models\SysLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            // Accesos rápidos solo en desarrollo (usuarios del seeder)
            'demo' => app()->isLocal()
                ? User::activos()->orderBy('id')
                    ->get(['id', 'name', 'apellido_paterno', 'email', 'perfil'])
                    ->unique('perfil')
                    ->sortBy(fn ($u) => array_search($u->perfil, User::PERFILES, true))
                    ->values()
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'activo' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas o usuario inactivo.']);
        }

        $request->session()->regenerate();
        SysLog::registrar('sesiones', Auth::id(), 'INSERT', 'Inicio de sesión');

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
