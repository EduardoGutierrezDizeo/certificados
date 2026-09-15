<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\CertificateRequest;
use Illuminate\Http\Request;

class CertificateRequestController extends Controller
{
    public function complete(Request $request, CertificateRequest $certificateRequest)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:success,failed'],
            'error_message' => ['required_if:status,failed', 'nullable', 'string'],
            'pdf' => ['required_if:status,success', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $updateData = ['duration_seconds' => $validated['duration_seconds'] ?? null];

        if ($validated['status'] === 'success') {
            $path = $request->file('pdf')->store(
                "certificates/{$certificateRequest->consultation_request_id}",
                'local'
            );

            $updateData['status'] = 'success';
            $updateData['pdf_path'] = $path;
            $updateData['pdf_generated_at'] = now();
            $updateData['error_message'] = null;
        } else {
            $updateData['status'] = 'failed';
            $updateData['error_message'] = $validated['error_message'];
            $updateData['pdf_path'] = null;
        }

        $certificateRequest->update($updateData);

        $certificateRequest->consultationRequest->refreshStatus();

        return response()->json(['ok' => true]);
    }
}
