<?php

namespace App\Services;

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class LawyerStorageService
{
    public function limit(): int
    {
        return (int) config('certificates.storage_limit');
    }

    public function usedCount(User $lawyer): int
    {
        return CertificateRequest::query()
            ->where('status', 'success')
            ->whereNotNull('pdf_path')
            ->whereHas('consultationRequest', function ($query) use ($lawyer): void {
                $query->withoutGlobalScopes()->where('lawyer_id', $lawyer->id);
            })
            ->count();
    }

    public function hasSpaceFor(User $lawyer, int $n): bool
    {
        return $this->usedCount($lawyer) + $n <= $this->limit();
    }

    /**
     * @return Collection<int, CertificateRequest>
     */
    public function oldestCertificatesToFree(User $lawyer, int $n, ?int $excludeConsultationRequestId = null): Collection
    {
        $query = CertificateRequest::query()
            ->where('status', 'success')
            ->whereNotNull('pdf_path')
            ->whereHas('consultationRequest', function ($query) use ($lawyer): void {
                $query->withoutGlobalScopes()->where('lawyer_id', $lawyer->id);
            })
            ->with('consultationRequest.subject')
            ->orderBy('pdf_generated_at')
            ->limit($n);

        if ($excludeConsultationRequestId !== null) {
            $query->where('consultation_request_id', '!=', $excludeConsultationRequestId);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, CertificateRequest>  $certificateRequests
     */
    public function freeCertificates(Collection $certificateRequests): void
    {
        foreach ($certificateRequests as $certificateRequest) {
            if ($certificateRequest->pdf_path !== null) {
                Storage::disk('local')->delete($certificateRequest->pdf_path);
            }

            $certificateRequest->update([
                'pdf_path' => null,
                'pdf_generated_at' => null,
            ]);
        }
    }

    public function freeConsultation(ConsultationRequest $consultation): void
    {
        $certificateRequests = $consultation->certificateRequests()
            ->whereNotNull('pdf_path')
            ->get();

        $this->freeCertificates($certificateRequests);
    }
}
