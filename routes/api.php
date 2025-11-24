<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Stores\StoreTerminalPaymentIntentController;
use App\Http\Controllers\Stores\StoreTerminalConnectionTokenController;
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public webhook endpoint (no authentication required)
Route::post('/stripe/connect/webhook', StripeConnectWebhookController::class)
    ->name('stripe.connect.webhook');

// Authentication endpoints (public)
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth endpoints
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('api.auth.logout-all');

    // Store endpoints
    Route::get('/stores', [StoreController::class, 'index'])->name('api.stores.index');
    // Current store routes must come before {slug} route to avoid conflicts
    Route::get('/stores/current', [StoreController::class, 'current'])->name('api.stores.current');
    Route::put('/stores/current', [StoreController::class, 'updateCurrent'])->name('api.stores.current.update');
    Route::patch('/stores/current', [StoreController::class, 'updateCurrent'])->name('api.stores.current.patch');
    Route::get('/stores/{slug}', [StoreController::class, 'show'])->name('api.stores.show');

    // POS Device endpoints (vendor-agnostic)
    Route::get('/pos-devices', [\App\Http\Controllers\Api\PosDevicesController::class, 'index'])->name('api.pos-devices.index');
    Route::post('/pos-devices', [\App\Http\Controllers\Api\PosDevicesController::class, 'store'])->name('api.pos-devices.store');
    Route::get('/pos-devices/{id}', [\App\Http\Controllers\Api\PosDevicesController::class, 'show'])->name('api.pos-devices.show');
    Route::put('/pos-devices/{id}', [\App\Http\Controllers\Api\PosDevicesController::class, 'update'])->name('api.pos-devices.update');
    Route::patch('/pos-devices/{id}', [\App\Http\Controllers\Api\PosDevicesController::class, 'update'])->name('api.pos-devices.patch');
    Route::post('/pos-devices/{id}/heartbeat', [\App\Http\Controllers\Api\PosDevicesController::class, 'heartbeat'])->name('api.pos-devices.heartbeat');

    // Terminal endpoints (Stripe-specific, require authentication)
    Route::get('/terminals/locations', [\App\Http\Controllers\Api\TerminalLocationsController::class, 'index'])->name('api.terminals.locations');
    Route::get('/terminals/readers', [\App\Http\Controllers\Api\TerminalReadersController::class, 'index'])->name('api.terminals.readers');
    Route::post('/stores/{store}/terminal/connection-token', StoreTerminalConnectionTokenController::class)
        ->name('stores.terminal.connection-token');
    Route::post('/stores/{store}/terminal/payment-intents', StoreTerminalPaymentIntentController::class)
        ->name('stores.terminal.payment-intents.store');

    // Tenant-scoped API resources
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomersController::class);
    
    // Note: Add more API resources here following the same pattern:
    // Route::apiResource('subscriptions', \App\Http\Controllers\Api\SubscriptionsController::class);
    // Route::apiResource('products', \App\Http\Controllers\Api\ProductsController::class);
    // Route::apiResource('charges', \App\Http\Controllers\Api\ChargesController::class);
    // etc.
});

// Legacy endpoint (kept for backward compatibility)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
