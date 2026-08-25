<?php

namespace App\Http\Controllers;

use App\Models\ErrorReport;
use Illuminate\Http\Request;

class ErrorReportController extends Controller
{
    public function create()
    {
        return view('error-reports.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'in:pago,certificado,otro'],
        ]);

        ErrorReport::create([
            'lawyer_id' => $request->user()->id,
            'status' => 'pending',
            ...$validated,
        ]);

        return back()->with('success', 'Reporte enviado correctamente.');
    }
}
