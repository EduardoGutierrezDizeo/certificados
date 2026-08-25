<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica tu correo electrónico')
            ->greeting('¡Hola!')
            ->line('Haz clic en el botón de abajo para confirmar tu dirección de correo electrónico y activar tu cuenta en CertiCheck.')
            ->action('Verificar correo electrónico', $url)
            ->line('Si no creó esta cuenta, puede ignorar este mensaje.');
    }
}
