<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\WompiSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WompiWebhookController extends Controller
{
    public function handle(Request $request, WompiSignatureService $signer)
    {
        $payload = $request->all();

        if (! $signer->verifyEventChecksum($payload)) {
            Log::warning('Webhook de Wompi rechazado: checksum inválido', ['payload' => $payload]);

            return response()->json(['ok' => false], 401);
        }

        $transaction = $payload['data']['transaction'] ?? null;
        if (! $transaction) {
            return response()->json(['ok' => true]);
        }

        $payment = Payment::where('reference', $transaction['reference'])->first();
        if (! $payment) {
            Log::warning('Webhook de Wompi: pago no encontrado', ['reference' => $transaction['reference']]);

            return response()->json(['ok' => true]);
        }

        $estadoMap = [
            'APPROVED' => 'approved',
            'DECLINED' => 'declined',
            'ERROR' => 'error',
            'VOIDED' => 'voided',
        ];

        $payment->update([
            'status' => $estadoMap[$transaction['status']] ?? 'error',
            'wompi_transaction_id' => $transaction['id'],
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
