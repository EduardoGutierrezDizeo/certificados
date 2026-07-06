<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\CertificateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificateRequestController extends Controller
{
    public function complete(Request $request, CertificateRequest $certificateRequest)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:success,failed'],
            'error_message' => ['required_if:status,failed', 'nullable', 'string'],
            'pdf' => ['required_if:status,success', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($validated['status'] === 'success') {
            $path = $request->file('pdf')->store(
                "certificates/{$certificateRequest->consultation_request_id}",
                'local'
            );

            $certificateRequest->update([
                'status' => 'success',
                'pdf_path' => $path,
                'error_message' => null,
            ]);
        } else {
            $certificateRequest->update([
                'status' => 'failed',
                'error_message' => $validated['error_message'],
                'pdf_path' => null,
            ]);
        }

        $certificateRequest->consultationRequest->refreshStatus();

        return response()->json(['ok' => true]);
    }
}