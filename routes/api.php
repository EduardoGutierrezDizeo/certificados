<?php

use App\Http\Controllers\EpaycoWebhookController;
use App\Http\Controllers\Internal\CertificateRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/epayco', [EpaycoWebhookController::class, 'handle'])->name('webhooks.epayco');

Route::middleware('internal.api')->prefix('internal')->group(function () {
    Route::post('/certificate-requests/{certificateRequest}/complete', [CertificateRequestController::class, 'complete']);
});
