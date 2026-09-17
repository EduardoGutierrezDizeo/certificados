<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Marca como declined los pagos pendientes sin confirmar por más de 30 minutos';

    public function handle(): int
    {
        $payments = Payment::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($payments as $payment) {
            $payload = $payment->raw_payload ?? [];
            $payload['expired_automatically_at'] = now()->toDateTimeString();
            $payload['expired_automatically_reason'] = 'No se recibió confirmación de ePayco dentro de los 30 minutos posteriores a la creación';

            $payment->update([
                'status' => 'declined',
                'raw_payload' => $payload,
            ]);
        }

        $this->info("Expirados {$payments->count()} pago(s) pendiente(s).");

        return self::SUCCESS;
    }
}
