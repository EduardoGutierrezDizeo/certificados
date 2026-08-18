<?php

namespace App\Services;

class EpaycoSignatureService
{
    public function verifyConfirmationSignature(array $payload): bool
    {
        $custIdCliente = config('services.epayco.cust_id_cliente');
        $pKey = config('services.epayco.p_key');

        $concatenated = implode('^', [
            $custIdCliente,
            $pKey,
            $payload['x_ref_payco'] ?? '',
            $payload['x_transaction_id'] ?? '',
            $payload['x_amount'] ?? '',
            $payload['x_currency_code'] ?? '',
        ]);

        $computedSignature = hash('sha256', $concatenated);
        $receivedSignature = $payload['x_signature'] ?? '';

        return hash_equals(strtolower($computedSignature), strtolower($receivedSignature));
    }
}
