<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LawyerController;
use App\Http\Controllers\Auth\ForcePasswordController;
use App\Http\Controllers\ConsultationRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::middleware('auth')->group(function () {
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
});

Route::middleware(['auth', 'verified', 'role:abogado'])->group(function () {
    Route::get('/subscribe', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::get('/subscribe/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscribe/return', [SubscriptionController::class, 'return'])->name('subscription.return');
    Route::get('/subscribe/status', [SubscriptionController::class, 'status'])->name('subscription.status');
    Route::get('/subscribe/history', [SubscriptionController::class, 'paymentHistory'])->name('subscription.history');
});

Route::middleware(['auth', 'verified', 'role:abogado', 'subscription.active'])->group(function () {
    Route::get('/dashboard', [ConsultationRequestController::class, 'create'])
        ->name('dashboard');

    Route::get('/consultation-requests/create', [ConsultationRequestController::class, 'create'])
        ->name('consultation-requests.create');

    Route::post('/consultation-requests', [ConsultationRequestController::class, 'store'])
        ->name('consultation-requests.store');

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

    Route::get('/consultation-requests/{consultationRequest}/download-zip', [ConsultationRequestController::class, 'downloadZip'])
        ->name('consultation-requests.download-zip');

    Route::get('/consultation-requests', [ConsultationRequestController::class, 'index'])
        ->name('consultation-requests.index');
});

require __DIR__.'/auth.php';
