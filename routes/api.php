<?php

use App\Http\Controllers\Internal\CertificateRequestController;
use App\Http\Controllers\WompiWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/wompi', [WompiWebhookController::class, 'handle'])->name('webhooks.wompi');

Route::middleware('internal.api')->prefix('internal')->group(function () {
    Route::post('/certificate-requests/{certificateRequest}/complete', [CertificateRequestController::class, 'complete']);
});
