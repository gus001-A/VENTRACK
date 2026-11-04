<?php

namespace App\Http\Controllers;

use App\Models\SysLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as Regla;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Recuperación de acceso: enlace por correo con vigencia y cambio de contraseña. */
class RecuperarController extends Controller
{
    /** Verifica que el correo pertenezca a un usuario activo y le envía el enlace. */
    public function enviar(Request $request): RedirectResponse
    {
        $email = $request->validate(['email' => ['required', 'email']], [], ['email' => 'correo'])['email'];

        $user = User::where('email', $email)->first();
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'No existe una cuenta registrada con este correo.']);
        }
        if (! $user->activo) {
            throw ValidationException::withMessages(['email' => 'Esta cuenta está inactiva. Comunícate con tu administrador.']);
        }

        $estado = Password::sendResetLink(['email' => $email, 'activo' => true]);
        if ($estado === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => 'Ya enviamos un enlace hace poco. Espera un minuto para solicitar otro.']);
        }

        return back()->with('success', "Te enviamos un enlace de recuperación a {$email}.");
    }

    public function formulario(Request $request, string $token): Response
    {
        return Inertia::render('Auth/Restablecer', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'vigencia' => config('auth.passwords.users.expire'),
        ]);
    }

    public function restablecer(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Regla::min(8)->letters()->numbers()],
        ], ['password.confirmed' => 'La confirmación de la contraseña no coincide.'], ['password' => 'nueva contraseña', 'email' => 'correo']);

        $estado = Password::reset(
            [...$request->only('email', 'password', 'password_confirmation', 'token'), 'activo' => true],
            function (User $user, string $password) {
                $user->auditNota = 'restableció su contraseña desde el correo de recuperación';
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                SysLog::registrar('users', $user->id, 'UPDATE', 'Contraseña restablecida por correo · '.$user->email);
                event(new PasswordReset($user));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => match ($estado) {
                Password::INVALID_TOKEN => 'El enlace no es válido o ya venció. Solicita uno nuevo.',
                default => 'No encontramos un usuario activo con ese correo.',
            }]);
        }

        return redirect('/login')->with('success', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
    }
}
