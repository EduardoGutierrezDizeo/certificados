<?php

namespace App\Notifications;

use App\Models\ErrorReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ErrorReportResolved extends Notification
{
    use Queueable;

    public function __construct(private ErrorReport $errorReport) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Tu reporte de error ha sido resuelto')
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu reporte \"{$this->errorReport->subject}\" ha sido resuelto por nuestro equipo de soporte.");

        if ($this->errorReport->admin_comment) {
            $mail->line("Comentario del equipo: {$this->errorReport->admin_comment}");
        }

        return $mail
            ->line('Si tienes alguna otra consulta, no dudes en contactarnos.');
    }
}
