<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmed extends Notification
{
    use Queueable;

    public function __construct(private Payment $payment) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $planNombre = $this->payment->subscriptionPlan?->name ?? 'estándar';
        $monto = '$'.number_format($this->payment->amount_in_cents / 100, 0, ',', '.');
        $fecha = $this->payment->updated_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

        return (new MailMessage)
            ->subject('¡Pago confirmado! Tu suscripción a CertiCheck ya está activa')
            ->greeting("Hola {$notifiable->name},")
            ->line("Hemos confirmado tu pago de {$monto} COP por el plan {$planNombre}.")
            ->line('Tu suscripción ya está activa y puedes comenzar a generar certificados con CertiCheck.')
            ->line("Fecha de confirmación: {$fecha}.")
            ->line('Si tienes alguna otra consulta, no dudes en contactarnos.');
    }
}
