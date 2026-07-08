<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateRequest;
use App\Models\ConsultationRequest;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalLawyers = User::role('abogado')->count();

        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('ends_at', '>=', now())
            ->count();

        $totalConsultations = ConsultationRequest::withoutGlobalScopes()->count();

        $successfulCertificates = CertificateRequest::where('status', 'success')->count();

        $totalRevenue = Payment::where('status', 'approved')
            ->sum('amount_in_cents') / 100;

        $monthlyRevenue = Payment::where('status', 'approved')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount_in_cents') / 100;

        $bySite = CertificateRequest::selectRaw('site, count(*) as total')
            ->where('status', 'success')
            ->groupBy('site')
            ->pluck('total', 'site');

        $lawyers = User::role('abogado')
            ->withCount('consultationRequests')
            ->addSelect(['last_consultation_at' => ConsultationRequest::select('created_at')
                ->whereColumn('lawyer_id', 'users.id')
                ->latest()
                ->limit(1),
            ])
            ->addSelect(['successful_certificates' => CertificateRequest::selectRaw('count(*)')
                ->join('consultation_requests', 'certificate_requests.consultation_request_id', '=', 'consultation_requests.id')
                ->whereColumn('consultation_requests.lawyer_id', 'users.id')
                ->where('certificate_requests.status', 'success'),
            ])
            ->orderBy('consultation_requests_count', 'desc')
            ->paginate(15)
            ->through(fn ($lawyer) => $lawyer->setAttribute(
                'last_consultation_at',
                $lawyer->last_consultation_at ? Carbon::parse($lawyer->last_consultation_at) : null,
            ));

        return view('admin.dashboard', compact(
            'totalLawyers',
            'activeSubscriptions',
            'totalConsultations',
            'successfulCertificates',
            'totalRevenue',
            'monthlyRevenue',
            'bySite',
            'lawyers',
        ));
    }
}
