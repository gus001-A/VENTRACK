<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo de recuperación de acceso con la imagen de VENTRACK. */
class RestablecerContrasena extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->email], false));

        return (new MailMessage)
            ->subject('Recupera tu acceso a VENTRACK')
            ->view('emails.restablecer', [
                'nombre' => $notifiable->name,
                'url' => $url,
                'minutos' => config('auth.passwords.users.expire'),
            ]);
    }
}
