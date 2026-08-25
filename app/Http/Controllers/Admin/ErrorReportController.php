<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorReport;
use App\Notifications\ErrorReportResolved;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErrorReportController extends Controller
{
    public function index(Request $request)
    {
        $query = ErrorReport::with('lawyer');

        if ($request->query('status') === 'pending') {
            $query->where('status', 'pending');
        } elseif ($request->query('status') === 'resolved') {
            $query->where('status', 'resolved');
        }

        $errorReports = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $pendingCount = ErrorReport::where('status', 'pending')->count();

        return view('admin.error-reports.index', compact('errorReports', 'pendingCount'));
    }

    public function show(ErrorReport $errorReport)
    {
        $errorReport->load(['lawyer', 'resolvedBy']);

        return view('admin.error-reports.show', compact('errorReport'));
    }

    public function resolve(Request $request, ErrorReport $errorReport)
    {
        abort_unless($errorReport->status === 'pending', 422, 'Este reporte ya fue resuelto.');

        $validated = $request->validate([
            'admin_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $errorReport->update([
            'status' => 'resolved',
            'admin_comment' => $validated['admin_comment'] ?? null,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        try {
            $errorReport->lawyer->notify(new ErrorReportResolved($errorReport));
        } catch (\Throwable $e) {
            Log::error("Error sending ErrorReportResolved notification for report #{$errorReport->id}: {$e->getMessage()}");

            return back()->with('success', 'Reporte marcado como resuelto.')
                ->with('warning', 'No se pudo enviar el correo de notificación. Puede reenviarlo desde el detalle del reporte.');
        }

        return back()->with('success', 'Reporte marcado como resuelto.');
    }

    public function resendNotification(ErrorReport $errorReport)
    {
        abort_unless($errorReport->status === 'resolved', 422, 'El reporte debe estar resuelto.');

        try {
            $errorReport->lawyer->notify(new ErrorReportResolved($errorReport));
        } catch (\Throwable $e) {
            Log::error("Error resending ErrorReportResolved notification for report #{$errorReport->id}: {$e->getMessage()}");

            return back()->with('error', 'No se pudo enviar el correo de notificación. Verifica la configuración de correo y reintenta.');
        }

        return back()->with('success', 'Correo de notificación reenviado correctamente.');
    }
}
