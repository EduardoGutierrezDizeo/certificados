<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Restablece tu contraseña')
            ->greeting('¡Hola!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en CertiCheck.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace expirará en {$expire} minutos.")
            ->line('Si no solicitó el restablecimiento de contraseña, puede ignorar este mensaje.');
    }
}
