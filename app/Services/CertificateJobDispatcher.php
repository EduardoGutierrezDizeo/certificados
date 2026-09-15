<?php

namespace App\Services;

use App\Models\CertificateRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CertificateJobDispatcher
{
    private const QUEUE_NAME = 'certificate_jobs';

    public function __construct(
        private readonly CertificateSitePriorityService $priorityService,
    ) {}

    public function dispatch(CertificateRequest $certificateRequest): void
    {
        $payload = $this->buildPayload($certificateRequest);

        Log::info('Certificate job dispatched', $payload);

        Redis::rpush(self::QUEUE_NAME, json_encode($payload));

        $certificateRequest->update(['status' => 'processing']);
    }

    /**
     * @param  Collection<int, CertificateRequest>  $certificateRequests
     */
    public function dispatchMultiple($certificateRequests): void
    {
        $sites = $certificateRequests->pluck('site')->unique()->values()->all();
        $sortedSites = $this->priorityService->getSitesSortedByDuration($sites);

        $sorted = $certificateRequests->sortBy(function (CertificateRequest $cr) use ($sortedSites): int {
            $index = array_search($cr->site, $sortedSites);

            return $index !== false ? $index : count($sortedSites);
        });

        foreach ($sorted as $certificateRequest) {
            $payload = $this->buildPayload($certificateRequest);

            Log::info('Certificate job dispatched', $payload);

            Redis::rpush(self::QUEUE_NAME, json_encode($payload));

            $certificateRequest->update(['status' => 'processing']);
        }
    }

    private function buildPayload(CertificateRequest $certificateRequest): array
    {
        $subject = $certificateRequest->consultationRequest->subject;

        return [
            'certificate_request_id' => $certificateRequest->id,
            'site' => $certificateRequest->site,
            'document_type' => $subject->document_type,
            'document_number' => $subject->document_number,
            'full_name' => $subject->full_name,
            'issuance_date' => $subject->issuance_date?->format('d/m/Y'),
        ];
    }
}
