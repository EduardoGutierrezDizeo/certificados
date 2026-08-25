<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ErrorReportController as AdminErrorReportController;
use App\Http\Controllers\Admin\LawyerController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Auth\ForcePasswordController;
use App\Http\Controllers\ConsultationRequestController;
use App\Http\Controllers\ErrorReportController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/terminos-y-condiciones', function () {
    return view('legal.terms');
})->name('legal.terms');

Route::get('/politica-de-datos', function () {
    return view('legal.privacy');
})->name('legal.privacy');

Route::middleware(['auth', 'single.session'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/force-password', [ForcePasswordController::class, 'edit'])->name('password.force.edit');
    Route::put('/force-password', [ForcePasswordController::class, 'update'])->name('password.force.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/lawyers', [LawyerController::class, 'index'])->name('lawyers.index');
    Route::get('/lawyers/create', [LawyerController::class, 'create'])->name('lawyers.create');
    Route::post('/lawyers', [LawyerController::class, 'store'])->name('lawyers.store');
    Route::post('/lawyers/{lawyer}/subscription/suspend', [LawyerController::class, 'suspendSubscription'])->name('lawyers.subscription.suspend');
    Route::post('/lawyers/{lawyer}/subscription/reactivate', [LawyerController::class, 'reactivateSubscription'])->name('lawyers.subscription.reactivate');
    Route::post('/lawyers/{lawyer}/subscription/cancel', [LawyerController::class, 'cancelSubscription'])->name('lawyers.subscription.cancel');
    Route::get('/lawyers/{lawyer}/payments', [LawyerController::class, 'payments'])->name('lawyers.payments');

    Route::get('/reportes', [AdminErrorReportController::class, 'index'])->name('error-reports.index');
    Route::get('/reportes/{errorReport}', [AdminErrorReportController::class, 'show'])->name('error-reports.show');
    Route::patch('/reportes/{errorReport}/resolver', [AdminErrorReportController::class, 'resolve'])->name('error-reports.resolve');
    Route::post('/reportes/{errorReport}/reenviar-notificacion', [AdminErrorReportController::class, 'resendNotification'])->name('error-reports.resend-notification');

    Route::get('/planes', [SubscriptionPlanController::class, 'index'])->name('subscription-plans.index');
    Route::get('/planes/crear', [SubscriptionPlanController::class, 'create'])->name('subscription-plans.create');
    Route::post('/planes', [SubscriptionPlanController::class, 'store'])->name('subscription-plans.store');
    Route::get('/planes/{subscriptionPlan}/editar', [SubscriptionPlanController::class, 'edit'])->name('subscription-plans.edit');
    Route::put('/planes/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->name('subscription-plans.update');
});

Route::middleware(['auth', 'role:abogado', 'single.session'])->prefix('session')->name('session.')->group(function () {
    Route::get('/heartbeat', [SessionController::class, 'heartbeat'])->name('heartbeat');
});

Route::middleware(['auth', 'role:abogado', 'single.session'])->group(function () {
    Route::get('/aceptar-terminos', [LegalController::class, 'show'])->name('legal.accept');
    Route::post('/aceptar-terminos', [LegalController::class, 'store'])->name('legal.accept.store');
});

Route::middleware(['auth', 'verified', 'role:abogado', 'single.session', 'terms.accepted'])->group(function () {
    Route::get('/subscribe', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::get('/subscribe/checkout/{subscriptionPlan}', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscribe/return', [SubscriptionController::class, 'return'])->name('subscription.return');
    Route::get('/subscribe/status', [SubscriptionController::class, 'status'])->name('subscription.status');

    Route::get('/mi-suscripcion', [SubscriptionController::class, 'manage'])->name('subscription.manage');
    Route::post('/mi-suscripcion/cancelar', [SubscriptionController::class, 'cancelSubscription'])->name('subscription.cancel');
});

Route::middleware(['auth', 'verified', 'role:abogado', 'single.session', 'terms.accepted'])->group(function () {
    Route::get('/reportar-problema', [ErrorReportController::class, 'create'])->name('error-reports.create');
    Route::post('/reportar-problema', [ErrorReportController::class, 'store'])->name('error-reports.store');
});

Route::middleware(['auth', 'verified', 'role:abogado', 'single.session', 'terms.accepted', 'subscription.active'])->group(function () {
    Route::get('/dashboard', [ConsultationRequestController::class, 'create'])
        ->name('dashboard');

    Route::get('/consultation-requests/create', [ConsultationRequestController::class, 'create'])
        ->name('consultation-requests.create');

    Route::post('/consultation-requests', [ConsultationRequestController::class, 'store'])
        ->name('consultation-requests.store');

    Route::get('/consultation-requests/status', [ConsultationRequestController::class, 'indexStatus'])
        ->name('consultation-requests.index-status');

    Route::get('/consultation-requests/{consultationRequest}', [ConsultationRequestController::class, 'show'])
        ->name('consultation-requests.show');

    Route::get('/consultation-requests/{consultationRequest}/status', [ConsultationRequestController::class, 'status'])
        ->name('consultation-requests.status');

    Route::get('/certificate-requests/{certificateRequest}/download', [ConsultationRequestController::class, 'download'])
        ->name('certificate-requests.download');

    Route::post('/certificate-requests/{certificateRequest}/retry', [ConsultationRequestController::class, 'retry'])
        ->name('certificate-requests.retry');

    Route::delete('/consultation-requests/{consultationRequest}', [ConsultationRequestController::class, 'destroy'])
        ->name('consultation-requests.destroy');

    Route::post('/consultation-requests/{consultationRequest}/regenerate', [ConsultationRequestController::class, 'regenerate'])
        ->name('consultation-requests.regenerate');

    Route::post('/consultation-requests/{consultationRequest}/cancel', [ConsultationRequestController::class, 'cancel'])
        ->name('consultation-requests.cancel');

    Route::get('/consultation-requests/{consultationRequest}/download-zip', [ConsultationRequestController::class, 'downloadZip'])
        ->name('consultation-requests.download-zip');

    Route::get('/consultation-requests', [ConsultationRequestController::class, 'index'])
        ->name('consultation-requests.index');
});

require __DIR__.'/auth.php';
