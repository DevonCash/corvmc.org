<?php

use App\Http\Controllers\Api\ZeffyWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Zapier webhook endpoint for Zeffy donations - no authentication required for webhooks
Route::post('/webhooks/zeffy', [ZeffyWebhookController::class, 'handleWebhook'])
    ->name('webhooks.zeffy');