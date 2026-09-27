<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Recupera tu acceso a Nexo')
            ->view('emails.account-security', [
                'name' => $notifiable->name,
                'heading' => 'Recupera tu acceso',
                'intro' => 'Recibimos una solicitud para cambiar la contraseña de tu cuenta Nexo.',
                'actionUrl' => $url,
                'actionLabel' => 'Crear contraseña nueva',
                'note' => 'El enlace vence en 60 minutos y solo puede utilizarse una vez.',
                'security' => 'Si no solicitaste este cambio, ignora el correo. Tu contraseña no cambiará.',
            ]);
    }
}
