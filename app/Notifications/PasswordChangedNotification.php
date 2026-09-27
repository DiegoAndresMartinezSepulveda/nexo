<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu contraseña de Nexo cambió')
            ->view('emails.account-security', [
                'name' => $notifiable->name,
                'heading' => 'Contraseña actualizada',
                'intro' => 'La contraseña de tu cuenta Nexo se cambió correctamente.',
                'actionUrl' => url('/app/'),
                'actionLabel' => 'Ir a Nexo',
                'note' => 'Si hiciste este cambio, no necesitas hacer nada más.',
                'security' => 'Si no lo reconoces, avisa de inmediato a la persona administradora de tu instalación.',
            ]);
    }
}
