<?php

use App\Http\Controllers\Stores\StoreTerminalPaymentIntentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use App\Http\Controllers\Stores\StoreTerminalConnectionTokenController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/stripe/connect/webhook', StripeConnectWebhookController::class)
    ->name('stripe.connect.webhook');

//Route::middleware([
//    // add whatever auth you use for your native app (Sanctum, JWT, etc.)
//])->group(function () {
    Route::post('/stores/{store}/terminal/connection-token', StoreTerminalConnectionTokenController::class)
        ->name('stores.terminal.connection-token');
    Route::post('/stores/{store}/terminal/payment-intents', StoreTerminalPaymentIntentController::class)
        ->name('stores.terminal.payment-intents.store');
//});
