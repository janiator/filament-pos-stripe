<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Stores\StoreTerminalPaymentIntentController;
use App\Http\Controllers\Stores\StoreTerminalConnectionTokenController;
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PosSessionsController;


// Public webhook endpoint (no authentication required)
// Support both /api/stripe/connect/webhook and /connectWebhook (for Stripe CLI compatibility)
Route::post('/stripe/connect/webhook', StripeConnectWebhookController::class)
    ->name('stripe.connect.webhook');
Route::post('/connectWebhook', StripeConnectWebhookController::class)
    ->name('stripe.connect.webhook.alias');

// Authentication endpoints (public)
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

// Product image serving with signed URLs (public route, secured by signature validation)
Route::get('/products/{product}/images/{media}', [\App\Http\Controllers\Api\ProductImagesController::class, 'serve'])
    ->name('api.products.images.serve');

// Collection image serving with signed URLs (public route, secured by signature validation)
Route::get('/collections/{collectionId}/image', [\App\Http\Controllers\Api\CollectionImagesController::class, 'serve'])
    ->name('api.collections.image.serve');

// SAF-T file download with signed URLs (public route, secured by signature validation)
Route::get('/saf-t/download/{filename}', [\App\Http\Controllers\Api\SafTController::class, 'download'])
    ->name('api.saf-t.download');


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
    Route::post('/pos-devices/{id}/start', [\App\Http\Controllers\Api\PosDevicesController::class, 'start'])->name('api.pos-devices.start');
    Route::post('/pos-devices/{id}/shutdown', [\App\Http\Controllers\Api\PosDevicesController::class, 'shutdown'])->name('api.pos-devices.shutdown');
    Route::post('/pos-devices/{id}/cash-drawer/open', [\App\Http\Controllers\Api\PosDevicesController::class, 'openCashDrawer'])->name('api.pos-devices.cash-drawer.open');
    Route::post('/pos-devices/{id}/cash-drawer/close', [\App\Http\Controllers\Api\PosDevicesController::class, 'closeCashDrawer'])->name('api.pos-devices.cash-drawer.close');

    // POS Session endpoints (Kassasystemforskriften compliance)
    Route::get('/pos-sessions', [\App\Http\Controllers\Api\PosSessionsController::class, 'index'])->name('api.pos-sessions.index');
    Route::get('/pos-sessions/current', [\App\Http\Controllers\Api\PosSessionsController::class, 'current'])->name('api.pos-sessions.current');
    Route::post('/pos-sessions/open', [\App\Http\Controllers\Api\PosSessionsController::class, 'open'])->name('api.pos-sessions.open');
    Route::post('/pos-sessions/{id}/close', [\App\Http\Controllers\Api\PosSessionsController::class, 'close'])->name('api.pos-sessions.close');
    Route::post('/pos-sessions/{id}/x-report', [\App\Http\Controllers\Api\PosSessionsController::class, 'xReport'])->name('api.pos-sessions.x-report');
    Route::post('/pos-sessions/{id}/z-report', [\App\Http\Controllers\Api\PosSessionsController::class, 'zReport'])->name('api.pos-sessions.z-report');
    Route::get('/pos-sessions/{id}', [\App\Http\Controllers\Api\PosSessionsController::class, 'show'])->name('api.pos-sessions.show');
    Route::post('/pos-sessions/daily-closing', [\App\Http\Controllers\Api\PosSessionsController::class, 'createDailyClosing'])->name('api.pos-sessions.daily-closing');

    // POS Report PDF download endpoints (API with Bearer token auth)
    Route::get('/pos-sessions/{id}/x-report/pdf', [\App\Http\Controllers\ReportController::class, 'downloadXReportPdfApi'])->name('api.pos-sessions.x-report.pdf');
    Route::get('/pos-sessions/{id}/z-report/pdf', [\App\Http\Controllers\ReportController::class, 'downloadZReportPdfApi'])->name('api.pos-sessions.z-report.pdf');

    // Terminal endpoints (Stripe-specific, require authentication)
    Route::get('/terminals/locations', [\App\Http\Controllers\Api\TerminalLocationsController::class, 'index'])->name('api.terminals.locations');
    Route::get('/terminals/readers', [\App\Http\Controllers\Api\TerminalReadersController::class, 'index'])->name('api.terminals.readers');
    Route::post('/stores/{store}/terminal/connection-token', StoreTerminalConnectionTokenController::class)
        ->name('stores.terminal.connection-token');
    Route::post('/stores/{store}/terminal/payment-intents', StoreTerminalPaymentIntentController::class)
        ->name('stores.terminal.payment-intents.store');

    // Tenant-scoped API resources
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomersController::class);
    Route::apiResource('products', \App\Http\Controllers\Api\ProductsController::class)->only(['index', 'show', 'store', 'update']);
    Route::apiResource('collections', \App\Http\Controllers\Api\CollectionsController::class)->only(['index', 'show', 'store', 'update']);
    Route::apiResource('vendors', \App\Http\Controllers\Api\VendorsController::class)->only(['index', 'show', 'store', 'update']);
    Route::get('/quantity-units', [\App\Http\Controllers\Api\QuantityUnitsController::class, 'index'])->name('api.quantity-units.index');

    // Inventory management endpoints
    Route::get('/products/{product}/inventory', [\App\Http\Controllers\Api\InventoryController::class, 'getProductInventory'])->name('api.products.inventory');
    Route::put('/variants/{variant}/inventory', [\App\Http\Controllers\Api\InventoryController::class, 'updateVariant'])->name('api.variants.inventory.update');
    Route::post('/variants/{variant}/inventory/adjust', [\App\Http\Controllers\Api\InventoryController::class, 'adjustInventory'])->name('api.variants.inventory.adjust');
    Route::post('/variants/{variant}/inventory/set', [\App\Http\Controllers\Api\InventoryController::class, 'setInventory'])->name('api.variants.inventory.set');
    Route::post('/inventory/bulk-update', [\App\Http\Controllers\Api\InventoryController::class, 'bulkUpdate'])->name('api.inventory.bulk-update');

    // SAF-T endpoints (Kassasystemforskriften compliance)
    Route::post('/saf-t/generate', [\App\Http\Controllers\Api\SafTController::class, 'generate'])->name('api.saf-t.generate');
    Route::get('/saf-t/content', [\App\Http\Controllers\Api\SafTController::class, 'content'])->name('api.saf-t.content');

    // POS Event endpoints
    Route::get('/pos-events', [\App\Http\Controllers\Api\PosEventsController::class, 'index'])->name('api.pos-events.index');
    Route::post('/pos-events', [\App\Http\Controllers\Api\PosEventsController::class, 'store'])->name('api.pos-events.store');
    Route::get('/pos-events/{id}', [\App\Http\Controllers\Api\PosEventsController::class, 'show'])->name('api.pos-events.show');

    // POS Line Corrections endpoints
    Route::get('/pos-line-corrections', [\App\Http\Controllers\Api\PosLineCorrectionsController::class, 'index'])->name('api.pos-line-corrections.index');
    Route::post('/pos-line-corrections', [\App\Http\Controllers\Api\PosLineCorrectionsController::class, 'store'])->name('api.pos-line-corrections.store');
    Route::get('/pos-line-corrections/{id}', [\App\Http\Controllers\Api\PosLineCorrectionsController::class, 'show'])->name('api.pos-line-corrections.show');

    // POS Transaction endpoints
    Route::post('/pos-transactions/charges/{chargeId}/void', [\App\Http\Controllers\Api\PosTransactionsController::class, 'void'])->name('api.pos-transactions.void');
    Route::post('/pos-transactions/correction-receipt', [\App\Http\Controllers\Api\PosTransactionsController::class, 'correctionReceipt'])->name('api.pos-transactions.correction-receipt');

    // Receipt endpoints
    Route::get('/receipts', [\App\Http\Controllers\Api\ReceiptsController::class, 'index'])->name('api.receipts.index');
    Route::post('/receipts/generate', [\App\Http\Controllers\Api\ReceiptsController::class, 'generate'])->name('api.receipts.generate');
    Route::get('/receipts/{id}', [\App\Http\Controllers\Api\ReceiptsController::class, 'show'])->name('api.receipts.show');
    Route::get('/receipts/{id}/xml', [\App\Http\Controllers\Api\ReceiptsController::class, 'xml'])->name('api.receipts.xml');
    Route::post('/receipts/{id}/mark-printed', [\App\Http\Controllers\Api\ReceiptsController::class, 'markPrinted'])->name('api.receipts.mark-printed');
    Route::post('/receipts/{id}/reprint', [\App\Http\Controllers\Api\ReceiptsController::class, 'reprint'])->name('api.receipts.reprint');

    // Product Declaration endpoints
    Route::get('/product-declaration', [\App\Http\Controllers\Api\ProductDeclarationController::class, 'show'])->name('api.product-declaration.show');
    Route::get('/product-declaration/display', [\App\Http\Controllers\Api\ProductDeclarationController::class, 'display'])->name('api.product-declaration.display');

    // Receipt Printer endpoints
    Route::get('/receipt-printers', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'index'])->name('api.receipt-printers.index');
    Route::post('/receipt-printers', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'store'])->name('api.receipt-printers.store');
    Route::get('/receipt-printers/{id}', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'show'])->name('api.receipt-printers.show');
    Route::put('/receipt-printers/{id}', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'update'])->name('api.receipt-printers.update');
    Route::patch('/receipt-printers/{id}', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'update'])->name('api.receipt-printers.patch');
    Route::delete('/receipt-printers/{id}', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'destroy'])->name('api.receipt-printers.destroy');
    Route::post('/receipt-printers/{id}/test-connection', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'testConnection'])->name('api.receipt-printers.test-connection');
    Route::post('/receipt-printers/{id}/test-print', [\App\Http\Controllers\Api\ReceiptPrintersController::class, 'testPrint'])->name('api.receipt-printers.test-print');

    // Purchase endpoints
    Route::get('/purchases/payment-methods', [\App\Http\Controllers\Api\PurchasesController::class, 'getPaymentMethods'])->name('api.purchases.payment-methods');
    Route::get('/purchases', [\App\Http\Controllers\Api\PurchasesController::class, 'index'])->name('api.purchases.index');
    Route::get('/purchases/{id}', [\App\Http\Controllers\Api\PurchasesController::class, 'show'])->name('api.purchases.show');
    Route::post('/purchases', [\App\Http\Controllers\Api\PurchasesController::class, 'store'])->name('api.purchases.store');
    Route::post('/purchases/{id}/complete-payment', [\App\Http\Controllers\Api\PurchasesController::class, 'completePayment'])->name('api.purchases.complete-payment');
    Route::post('/purchases/{id}/cancel', [\App\Http\Controllers\Api\PurchasesController::class, 'cancel'])->name('api.purchases.cancel');
    Route::post('/purchases/{id}/refund', [\App\Http\Controllers\Api\PurchasesController::class, 'refund'])->name('api.purchases.refund');
    Route::put('/purchases/{id}/customer', [\App\Http\Controllers\Api\PurchasesController::class, 'updateCustomer'])->name('api.purchases.update-customer');
    Route::patch('/purchases/{id}/customer', [\App\Http\Controllers\Api\PurchasesController::class, 'updateCustomer'])->name('api.purchases.update-customer.patch');

    // Gift card endpoints
    Route::post('/gift-cards/purchase', [\App\Http\Controllers\Api\GiftCardsController::class, 'purchase'])->name('api.gift-cards.purchase');
    Route::post('/gift-cards/validate', [\App\Http\Controllers\Api\GiftCardsController::class, 'validateGiftCard'])->name('api.gift-cards.validate');
    Route::get('/gift-cards', [\App\Http\Controllers\Api\GiftCardsController::class, 'index'])->name('api.gift-cards.index');
    Route::get('/gift-cards/{code}', [\App\Http\Controllers\Api\GiftCardsController::class, 'show'])->name('api.gift-cards.show');
    Route::get('/gift-cards/{id}/transactions', [\App\Http\Controllers\Api\GiftCardsController::class, 'transactions'])->name('api.gift-cards.transactions');
    Route::post('/gift-cards/{id}/refund', [\App\Http\Controllers\Api\GiftCardsController::class, 'refund'])->name('api.gift-cards.refund');
    Route::post('/gift-cards/{id}/void', [\App\Http\Controllers\Api\GiftCardsController::class, 'void'])->name('api.gift-cards.void');
    Route::post('/gift-cards/{id}/adjust-balance', [\App\Http\Controllers\Api\GiftCardsController::class, 'adjustBalance'])->name('api.gift-cards.adjust-balance');

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
