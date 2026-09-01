<?php

use App\Http\Controllers\AsaasWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'application' => config('app.name', 'AssessorGov IA'),
        'status' => 'ok',
    ]);
});

Route::post('/webhooks/asaas', AsaasWebhookController::class)
    ->name('webhooks.asaas');
