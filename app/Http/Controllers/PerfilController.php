<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Perfil del usuario autenticado: cambio de correo y de contraseña (con su contraseña actual). */
class PerfilController extends Controller
{
    public function correo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password_actual' => ['required', 'current_password'],
        ], [
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
        ], ['email' => 'correo', 'password_actual' => 'contraseña actual']);

        $user->auditNota = 'cambió su correo';
        $user->update(['email' => $data['email']]);

        return back()->with('success', 'Tu correo se actualizó');
    }

    public function contrasena(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:password_actual', Password::min(8)->letters()->numbers()],
        ], [
            'password_actual.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ], ['password' => 'nueva contraseña', 'password_actual' => 'contraseña actual']);

        $user->auditNota = 'cambió su contraseña';
        $user->update(['password' => $data['password']]);

        return back()->with('success', 'Tu contraseña se actualizó');
    }
}
