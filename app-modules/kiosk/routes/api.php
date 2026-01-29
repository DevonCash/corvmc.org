<?php

use CorvMC\Kiosk\Http\Controllers\AuthController;
use CorvMC\Kiosk\Http\Controllers\CheckInController;
use CorvMC\Kiosk\Http\Controllers\DeviceController;
use CorvMC\Kiosk\Http\Controllers\DoorController;
use CorvMC\Kiosk\Http\Controllers\EventController;
use CorvMC\Kiosk\Http\Controllers\PaymentRequestController;
use CorvMC\Kiosk\Http\Controllers\TerminalController;
use CorvMC\Kiosk\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/kiosk')->middleware('kiosk.device')->group(function () {
    // Device verification (no user auth required)
    Route::get('/device/verify', [DeviceController::class, 'verify']);

    // User login (device key required, no user token yet)
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes (device key + user token required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        // Events
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::get('/events/{event}/stats', [EventController::class, 'stats']);
        Route::get('/events/{event}/pricing', [DoorController::class, 'pricing']);

        // Check-in
        Route::post('/events/{event}/check-in', [CheckInController::class, 'checkIn']);
        Route::get('/events/{event}/recent-check-ins', [CheckInController::class, 'recentCheckIns']);

        // Door sales
        Route::post('/events/{event}/door-sale', [DoorController::class, 'createSale']);
        Route::post('/events/{event}/door-sale/payment-intent', [DoorController::class, 'createPaymentIntent']);
        Route::post('/events/{event}/door-sale/capture', [DoorController::class, 'capturePayment']);
        Route::get('/events/{event}/recent-sales', [DoorController::class, 'recentSales']);

        // Stripe Terminal
        Route::post('/terminal/connection-token', [TerminalController::class, 'connectionToken']);

        // Payment requests - specific routes before wildcard
        Route::post('/payment-requests', [PaymentRequestController::class, 'create']);
        Route::get('/payment-requests/pending', [PaymentRequestController::class, 'pending']);
        Route::get('/payment-requests/{paymentRequest}', [PaymentRequestController::class, 'show']);
        Route::post('/payment-requests/{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel']);
        Route::post('/payment-requests/{paymentRequest}/collect', [PaymentRequestController::class, 'startCollection']);
        Route::post('/payment-requests/{paymentRequest}/complete', [PaymentRequestController::class, 'complete']);
        Route::post('/payment-requests/{paymentRequest}/fail', [PaymentRequestController::class, 'fail']);

        // User lookup
        Route::get('/users/lookup', [UserController::class, 'lookup']);
    });
});
