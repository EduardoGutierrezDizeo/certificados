<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Internal\CertificateRequestController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('internal.api')->prefix('internal')->group(function () {
    Route::post('/certificate-requests/{certificateRequest}/complete', [CertificateRequestController::class, 'complete']);
});