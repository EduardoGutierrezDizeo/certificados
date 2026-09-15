<?php

namespace App\Http\Controllers;

use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\User;
use App\Services\LawyerStorageService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $sortQuery = collect($request->query())
            ->except('page')
            ->merge(['sort' => 'date', 'dir' => $dir === 'asc' ? 'desc' : 'asc'])
            ->all();

        $sortUrl = route('storage.index', $sortQuery);

        if ($view === 'individual') {
            $certificates = $this->buildIndividualQuery($request->user(), $dir);

            return view('storage.index', compact('used', 'limit', 'certificates', 'dir', 'sortUrl'))
                ->with('view', 'individual');
        }

        $consultations = $this->buildGroupedQuery($request->user(), $dir);

        return view('storage.index', compact('used', 'limit', 'consultations', 'dir', 'sortUrl'))
            ->with('view', 'grouped');
    }

    public function data(Request $request)
    {
        $storage = app(LawyerStorageService::class);

        $view = $request->query('view', 'grouped') === 'individual' ? 'individual' : 'grouped';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $paginated = $view === 'individual'
            ? $this->buildIndividualQuery($request->user(), $dir)
            : $this->buildGroupedQuery($request->user(), $dir);

        return response()->json([
            'view' => $view,
            'used' => $storage->usedCount($request->user()),
            'limit' => $storage->limit(),
            ...$paginated->toArray(),
        ]);
    }

    public function destroyCertificate(Request $request, CertificateRequest $certificateRequest)
    {
        $ownerId = ConsultationRequest::query()
            ->withoutGlobalScopes()
            ->whereKey($certificateRequest->consultation_request_id)
            ->value('lawyer_id');

        $perteneceAlAbogado = $ownerId === auth()->id();
        $esAdmin = auth()->user()->hasRole('admin');
        abort_unless($perteneceAlAbogado || $esAdmin, 403);

        app(LawyerStorageService::class)->freeCertificates(new Collection([$certificateRequest]));

        if ($request->expectsJson()) {
            return response()->json($this->deletionPayload($request));
        }

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

        if ($request->expectsJson()) {
            return response()->json($this->deletionPayload($request));
        }

        return redirect()->route('storage.index')->with('status', 'Certificados liberados correctamente.');
    }

    public function destroyConsultation(Request $request, ConsultationRequest $consultationRequest)
    {
        $perteneceAlAbogado = $consultationRequest->lawyer_id === auth()->id();
        $esAdmin = auth()->user()->hasRole('admin');
        abort_unless($perteneceAlAbogado || $esAdmin, 403);

        app(LawyerStorageService::class)->freeConsultation($consultationRequest);

        if ($request->expectsJson()) {
            return response()->json($this->deletionPayload($request));
        }

        return redirect()->route('storage.index')->with('status', 'Almacenamiento de la consulta liberado correctamente.');
    }

    /**
     * @return LengthAwarePaginator<int, CertificateRequest>
     */
    private function buildIndividualQuery(User $lawyer, string $dir): LengthAwarePaginator
    {
        $certificates = CertificateRequest::query()
            ->whereNotNull('pdf_path')
            ->whereHas('consultationRequest', function ($query) use ($lawyer): void {
                $query->where('lawyer_id', $lawyer->id);
            })
            ->with('consultationRequest.subject')
            ->orderBy('pdf_generated_at', $dir)
            ->paginate(30)
            ->withQueryString();

        $certificates->getCollection()->transform(function (CertificateRequest $certificate): CertificateRequest {
            $certificate->size_bytes = $this->fileSize($certificate);
            $certificate->size_label = $this->formatBytes($certificate->size_bytes);

            return $certificate;
        });

        return $certificates;
    }

    /**
     * @return LengthAwarePaginator<int, ConsultationRequest>
     */
    private function buildGroupedQuery(User $lawyer, string $dir): LengthAwarePaginator
    {
        $consultations = ConsultationRequest::query()
            ->where('lawyer_id', $lawyer->id)
            ->whereHas('certificateRequests', fn ($query) => $query->whereNotNull('pdf_path'))
            ->with(['subject', 'certificateRequests'])
            ->orderBy('created_at', $dir)
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

        return $consultations;
    }

    /**
     * Payload común para respuestas JSON de los endpoints de borrado.
     */
    private function deletionPayload(Request $request): array
    {
        $storage = app(LawyerStorageService::class);
        $user = auth()->user();

        [$used, $limit] = [$storage->usedCount($user), $storage->limit()];

        $payload = [
            'ok' => true,
            'used' => $used,
            'limit' => $limit,
        ];

        if ($request->has('needed')) {
            $payload['has_space'] = $storage->hasSpaceFor($user, (int) $request->input('needed'));
        }

        return $payload;
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
