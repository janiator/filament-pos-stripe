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

// Product image serving with signed URLs (public route, secured by signature validation)
Route::get('/products/{product}/images/{media}', [\App\Http\Controllers\Api\ProductImagesController::class, 'serve'])
    ->name('api.products.images.serve');

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

    // POS Session endpoints (Kassasystemforskriften compliance)
    Route::get('/pos-sessions', [\App\Http\Controllers\Api\PosSessionsController::class, 'index'])->name('api.pos-sessions.index');
    Route::get('/pos-sessions/current', [\App\Http\Controllers\Api\PosSessionsController::class, 'current'])->name('api.pos-sessions.current');
    Route::post('/pos-sessions/open', [\App\Http\Controllers\Api\PosSessionsController::class, 'open'])->name('api.pos-sessions.open');
    Route::post('/pos-sessions/{id}/close', [\App\Http\Controllers\Api\PosSessionsController::class, 'close'])->name('api.pos-sessions.close');
    Route::post('/pos-sessions/{id}/x-report', [\App\Http\Controllers\Api\PosSessionsController::class, 'xReport'])->name('api.pos-sessions.x-report');
    Route::post('/pos-sessions/{id}/z-report', [\App\Http\Controllers\Api\PosSessionsController::class, 'zReport'])->name('api.pos-sessions.z-report');
    Route::get('/pos-sessions/{id}', [\App\Http\Controllers\Api\PosSessionsController::class, 'show'])->name('api.pos-sessions.show');
    Route::post('/pos-sessions/daily-closing', [\App\Http\Controllers\Api\PosSessionsController::class, 'createDailyClosing'])->name('api.pos-sessions.daily-closing');

    // Terminal endpoints (Stripe-specific, require authentication)
    Route::get('/terminals/locations', [\App\Http\Controllers\Api\TerminalLocationsController::class, 'index'])->name('api.terminals.locations');
    Route::get('/terminals/readers', [\App\Http\Controllers\Api\TerminalReadersController::class, 'index'])->name('api.terminals.readers');
    Route::post('/stores/{store}/terminal/connection-token', StoreTerminalConnectionTokenController::class)
        ->name('stores.terminal.connection-token');
    Route::post('/stores/{store}/terminal/payment-intents', StoreTerminalPaymentIntentController::class)
        ->name('stores.terminal.payment-intents.store');

    // Tenant-scoped API resources
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomersController::class);
    Route::apiResource('products', \App\Http\Controllers\Api\ProductsController::class)->only(['index', 'show']);
    
    // Inventory management endpoints
    Route::get('/products/{product}/inventory', [\App\Http\Controllers\Api\InventoryController::class, 'getProductInventory'])->name('api.products.inventory');
    Route::put('/variants/{variant}/inventory', [\App\Http\Controllers\Api\InventoryController::class, 'updateVariant'])->name('api.variants.inventory.update');
    Route::post('/variants/{variant}/inventory/adjust', [\App\Http\Controllers\Api\InventoryController::class, 'adjustInventory'])->name('api.variants.inventory.adjust');
    Route::post('/variants/{variant}/inventory/set', [\App\Http\Controllers\Api\InventoryController::class, 'setInventory'])->name('api.variants.inventory.set');
    Route::post('/inventory/bulk-update', [\App\Http\Controllers\Api\InventoryController::class, 'bulkUpdate'])->name('api.inventory.bulk-update');
    
    // SAF-T endpoints (Kassasystemforskriften compliance)
    Route::post('/saf-t/generate', [\App\Http\Controllers\Api\SafTController::class, 'generate'])->name('api.saf-t.generate');
    Route::get('/saf-t/content', [\App\Http\Controllers\Api\SafTController::class, 'content'])->name('api.saf-t.content');
    Route::get('/saf-t/download/{filename}', [\App\Http\Controllers\Api\SafTController::class, 'download'])->name('api.saf-t.download');

    // POS Event endpoints
    Route::get('/pos-events', [\App\Http\Controllers\Api\PosEventsController::class, 'index'])->name('api.pos-events.index');
    Route::post('/pos-events', [\App\Http\Controllers\Api\PosEventsController::class, 'store'])->name('api.pos-events.store');
    Route::get('/pos-events/{id}', [\App\Http\Controllers\Api\PosEventsController::class, 'show'])->name('api.pos-events.show');

    // Receipt endpoints
    Route::get('/receipts', [\App\Http\Controllers\Api\ReceiptsController::class, 'index'])->name('api.receipts.index');
    Route::post('/receipts/generate', [\App\Http\Controllers\Api\ReceiptsController::class, 'generate'])->name('api.receipts.generate');
    Route::get('/receipts/{id}', [\App\Http\Controllers\Api\ReceiptsController::class, 'show'])->name('api.receipts.show');
    Route::get('/receipts/{id}/xml', [\App\Http\Controllers\Api\ReceiptsController::class, 'xml'])->name('api.receipts.xml');
    Route::post('/receipts/{id}/mark-printed', [\App\Http\Controllers\Api\ReceiptsController::class, 'markPrinted'])->name('api.receipts.mark-printed');
    Route::post('/receipts/{id}/reprint', [\App\Http\Controllers\Api\ReceiptsController::class, 'reprint'])->name('api.receipts.reprint');
    
    // Note: Add more API resources here following the same pattern:
    // Route::apiResource('subscriptions', \App\Http\Controllers\Api\SubscriptionsController::class);
    // Route::apiResource('charges', \App\Http\Controllers\Api\ChargesController::class);
    // etc.
});

// Legacy endpoint (kept for backward compatibility)
Route::get('/user', function (Request $request) {
    $user = $request->user();
    return response()->json($user);
})->middleware('auth:sanctum');
