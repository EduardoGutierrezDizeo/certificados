<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\EpaycoSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EpaycoWebhookController extends Controller
{
    public function handle(Request $request, EpaycoSignatureService $signer)
    {
        $payload = $request->all();

        if (! $signer->verifyConfirmationSignature($payload)) {
            Log::warning('Webhook de ePayco rechazado: firma inválida', ['payload' => $payload]);

            return response()->json(['ok' => false], 401);
        }

        $reference = $payload['x_id_factura'] ?? null;
        if (! $reference) {
            return response()->json(['ok' => true]);
        }

        $payment = Payment::where('reference', $reference)->first();
        if (! $payment) {
            Log::warning('Webhook de ePayco: pago no encontrado', ['reference' => $reference]);

            return response()->json(['ok' => true]);
        }

        $estadoMap = [
            'Aceptada' => 'approved',
            'Pendiente' => 'pending',
            'Rechazada' => 'declined',
            'Fallida' => 'error',
            'Reversada' => 'voided',
            'Retenida' => 'error',
            'Iniciada' => 'pending',
        ];

        $payment->update([
            'status' => $estadoMap[$payload['x_transaction_state'] ?? ''] ?? 'error',
            'gateway_transaction_id' => $payload['x_ref_payco'] ?? null,
            'raw_payload' => $payload,
        ]);

        if ($payment->status === 'approved') {
            $this->activarSuscripcion($payment);
        }

        return response()->json(['ok' => true]);
    }

    private function activarSuscripcion(Payment $payment): void
    {
        $user = $payment->user;
        $suscripcionActiva = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>=', now())
            ->latest()
            ->first();

        if ($suscripcionActiva) {
            $suscripcionActiva->update([
                'ends_at' => $suscripcionActiva->ends_at->addMonth(),
            ]);
        } else {
            $user->subscriptions()->create([
                'plan' => 'standard',
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);
        }
    }
}
