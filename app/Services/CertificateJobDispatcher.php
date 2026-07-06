<?php

namespace App\Services;

use App\Models\CertificateRequest;
use Illuminate\Support\Facades\Redis;

class CertificateJobDispatcher
{
    private const QUEUE_NAME = 'certificate_jobs';

    public function dispatch(CertificateRequest $certificateRequest): void
    {
        $subject = $certificateRequest->consultationRequest->subject;

        $payload = [
            'certificate_request_id' => $certificateRequest->id,
            'site' => $certificateRequest->site,
            'document_type' => $subject->document_type,
            'document_number' => $subject->document_number,
            'full_name' => $subject->full_name,
            'issuance_date' => $subject->issuance_date?->format('d/m/Y'),
        ];

        Redis::rpush(self::QUEUE_NAME, json_encode($payload));

        $certificateRequest->update(['status' => 'processing']);
    }
}