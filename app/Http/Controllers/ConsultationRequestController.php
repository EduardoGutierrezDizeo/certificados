<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConsultationRequestRequest;
use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Subject;
use App\Services\CertificateJobDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConsultationRequestController extends Controller
{
    public function create()
    {
        return view('consultation-requests.create');
    }

    public function store(StoreConsultationRequestRequest $request, CertificateJobDispatcher $dispatcher)
    {
        $validated = $request->validated();

        $consultationRequest = DB::transaction(function () use ($validated, $dispatcher) {
            $subject = Subject::firstOrCreate(
                [
                    'document_type' => $validated['document_type'],
                    'document_number' => $validated['document_number'],
                ],
                [
                    'full_name' => $validated['full_name'] ?? null,
                    'company_name' => $validated['company_name'] ?? null,
                    'issuance_date' => isset($validated['issuance_date'])
                        ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['issuance_date'])
                        : null,
                ]
            );

            $subject->fill(array_filter([
                'full_name' => $subject->full_name ?? ($validated['full_name'] ?? null),
                'issuance_date' => $subject->issuance_date ?? (isset($validated['issuance_date'])
                    ? \Carbon\Carbon::createFromFormat('Y-m-d', $validated['issuance_date'])
                    : null),
            ]))->save();

            $consultationRequest = ConsultationRequest::create([
                'subject_id' => $subject->id,
                'status' => 'pending',
            ]);

            foreach ($validated['sites'] as $site) {
                $certificateRequest = $consultationRequest->certificateRequests()->create([
                    'site' => $site,
                    'status' => 'pending',
                ]);

                $dispatcher->dispatch($certificateRequest);
            }

            return $consultationRequest;
        });

        return redirect()->route('consultation-requests.show', $consultationRequest);
    }

    public function show(ConsultationRequest $consultationRequest)
    {
        $consultationRequest->load('certificateRequests', 'subject');

        $consultationRequest->certificateRequests->transform(fn($cr) => $cr->setAttribute(
            'download_url',
            $cr->status === 'success' ? route('certificate-requests.download', $cr) : null,
        ));

        return view('consultation-requests.show', compact('consultationRequest'));
    }

    public function status(ConsultationRequest $consultationRequest)
    {
        $consultationRequest->load('certificateRequests');

        return response()->json([
            'status' => $consultationRequest->status,
            'certificates' => $consultationRequest->certificateRequests->map(fn($cr) => [
                'id' => $cr->id,
                'site' => $cr->site,
                'status' => $cr->status,
                'error_message' => $cr->error_message,
                'download_url' => $cr->status === 'success'
                    ? route('certificate-requests.download', $cr)
                    : null,
            ]),
        ]);
    }

    public function download(CertificateRequest $certificateRequest)
    {
        abort_unless($certificateRequest->status === 'success' && $certificateRequest->pdf_path, 404);

        $perteneceAlAbogado = $certificateRequest->consultationRequest->lawyer_id === auth()->id();
        $esAdmin = auth()->user()->hasRole('admin');
        abort_unless($perteneceAlAbogado || $esAdmin, 403);

        $etiquetasSitio = [
            'rnmc' => 'RNMC',
            'judicial_police' => 'Antecedentes Judiciales',
            'comptroller' => 'Contraloria',
            'attorney_general' => 'Procuraduria',
        ];

        $numeroDocumento = $certificateRequest->consultationRequest->subject->document_number;
        $etiqueta = $etiquetasSitio[$certificateRequest->site] ?? $certificateRequest->site;
        $nombreDescarga = "{$numeroDocumento} - {$etiqueta}.pdf";

        return Storage::download($certificateRequest->pdf_path, $nombreDescarga);
    }

    public function retry(CertificateRequest $certificateRequest, CertificateJobDispatcher $dispatcher)
    {
        $perteneceAlAbogado = $certificateRequest->consultationRequest->lawyer_id === auth()->id();
        abort_unless($perteneceAlAbogado, 403);
        abort_unless($certificateRequest->status === 'failed', 422);

        $certificateRequest->update(['status' => 'pending', 'error_message' => null]);
        $dispatcher->dispatch($certificateRequest);

        return response()->json(['ok' => true]);
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $consultationRequests = ConsultationRequest::query()
            ->with('subject')
            ->whereHas('subject', function ($query) use ($request) {
                if ($request->filled('document_number')) {
                    $query->where('document_number', 'like', '%'.$request->string('document_number').'%');
                }
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('consultation-requests.index', compact('consultationRequests'));
    }
}