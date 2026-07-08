<?php

namespace App\Services;

class WompiSignatureService
{
    public function integritySignature(string $reference, int $amountInCents, string $currency = 'COP'): string
    {
        $secret = config('services.wompi.integrity_secret');

        return hash('sha256', $reference.$amountInCents.$currency.$secret);
    }

    public function verifyEventChecksum(array $payload): bool
    {
        $secret = config('services.wompi.events_secret');

        $properties = $payload['signature']['properties'] ?? [];
        $timestamp = $payload['timestamp'] ?? '';
        $receivedChecksum = $payload['signature']['checksum'] ?? '';

        $concatenated = '';
        foreach ($properties as $propertyPath) {
            $concatenated .= data_get($payload['data'], $propertyPath);
        }
        $concatenated .= $timestamp;
        $concatenated .= $secret;

        $computedChecksum = hash('sha256', $concatenated);

        return hash_equals(strtolower($computedChecksum), strtolower($receivedChecksum));
    }
}
