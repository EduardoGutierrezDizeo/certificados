<?php

namespace App\Http\Controllers;

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Services\LawyerStorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorageController extends Controller
{
    public function index(Request $request)
    {
        $storage = app(LawyerStorageService::class);

        $used = $storage->usedCount($request->user());
        $limit = $storage->limit();

        $view = $request->query('view', 'grouped') === 'individual' ? 'individual' : 'grouped';

        if ($view === 'individual') {
            $certificates = CertificateRequest::query()
                ->whereNotNull('pdf_path')
                ->whereHas('consultationRequest', function ($query) use ($request): void {
                    $query->where('lawyer_id', $request->user()->id);
                })
                ->with('consultationRequest.subject')
                ->latest()
                ->paginate(30)
                ->withQueryString();

            $certificates->getCollection()->transform(function (CertificateRequest $certificate): CertificateRequest {
                $certificate->size_bytes = $this->fileSize($certificate);
                $certificate->size_label = $this->formatBytes($certificate->size_bytes);

                return $certificate;
            });

            return view('storage.index', compact('used', 'limit', 'certificates'))
                ->with('view', 'individual');
        }

        $consultations = ConsultationRequest::query()
            ->with(['subject', 'certificateRequests'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $consultations->getCollection()->transform(function (ConsultationRequest $consultation): ConsultationRequest {
            $requested = $consultation->certificateRequests;
            $withFile = $requested->filter(fn ($cr) => $cr->pdf_path !== null);

            $consultation->is_complete = $requested->isNotEmpty()
                && $requested->count() === $withFile->count();
            $consultation->total_size = (int) $withFile->sum(fn ($cr) => $this->fileSize($cr));
            $consultation->size_label = $this->formatBytes($consultation->total_size);

            return $consultation;
        });

        return view('storage.index', compact('used', 'limit', 'consultations'))
            ->with('view', 'grouped');
    }

    public function destroyCertificate(CertificateRequest $certificateRequest)
    {
        $ownerId = ConsultationRequest::query()
            ->withoutGlobalScopes()
            ->whereKey($certificateRequest->consultation_request_id)
            ->value('lawyer_id');

        $perteneceAlAbogado = $ownerId === auth()->id();
        $esAdmin = auth()->user()->hasRole('admin');
        abort_unless($perteneceAlAbogado || $esAdmin, 403);

        app(LawyerStorageService::class)->freeCertificates(new Collection([$certificateRequest]));

        return redirect()->route('storage.index')->with('status', 'Certificado liberado correctamente.');
    }

    public function destroyCertificatesBulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $ids = collect($validated['ids'])->map(fn ($id) => (int) $id)->all();

        $isAdmin = auth()->user()->hasRole('admin');

        if (! $isAdmin) {
            $ownedCount = CertificateRequest::query()
                ->whereIn('id', $ids)
                ->whereHas('consultationRequest', function ($query) use ($request): void {
                    $query->withoutGlobalScopes()->where('lawyer_id', $request->user()->id);
                })
                ->count();

            abort_unless($ownedCount === count($ids), 403);
        }

        $certificates = CertificateRequest::query()
            ->whereIn('id', $ids)
            ->get();

        app(LawyerStorageService::class)->freeCertificates($certificates);

        return redirect()->route('storage.index')->with('status', 'Certificados liberados correctamente.');
    }

    public function destroyConsultation(ConsultationRequest $consultationRequest)
    {
        $perteneceAlAbogado = $consultationRequest->lawyer_id === auth()->id();
        $esAdmin = auth()->user()->hasRole('admin');
        abort_unless($perteneceAlAbogado || $esAdmin, 403);

        app(LawyerStorageService::class)->freeConsultation($consultationRequest);

        return redirect()->route('storage.index')->with('status', 'Almacenamiento de la consulta liberado correctamente.');
    }

    private function fileSize(CertificateRequest $certificateRequest): int
    {
        if ($certificateRequest->pdf_path === null) {
            return 0;
        }

        try {
            return (int) Storage::disk('local')->size($certificateRequest->pdf_path);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', ' ').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KB';
        }

        return $bytes.' B';
    }
}
