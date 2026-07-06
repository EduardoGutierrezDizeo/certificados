<?php

use App\Http\Controllers\ConsultationRequestController;
use App\Http\Controllers\ProfileController;
use App\Models\ConsultationRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ForcePasswordController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/force-password', [ForcePasswordController::class, 'edit'])->name('password.force.edit');
    Route::put('/force-password', [ForcePasswordController::class, 'update'])->name('password.force.update');
});

Route::middleware(['auth', 'role:abogado'])->group(function () {
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

    Route::get('/consultation-requests', [ConsultationRequestController::class, 'index'])
    ->name('consultation-requests.index');
});

require __DIR__.'/auth.php';