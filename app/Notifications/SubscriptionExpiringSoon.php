<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification
{
    use Queueable;

    public function __construct(private Subscription $subscription) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $diasRestantes = now()->diffInDays($this->subscription->ends_at, false);

        return (new MailMessage)
            ->subject('Tu suscripción a CertiCheck vence pronto')
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu suscripción vence el {$this->subscription->ends_at->format('d/m/Y')} (en {$diasRestantes} días).")
            ->line('Para no perder acceso a la generación de certificados, renueva antes de esa fecha.')
            ->action('Renovar suscripción', route('subscription.show'))
            ->line('Si ya renovaste, puedes ignorar este mensaje.');
    }
}
