<?php

declare(strict_types=1);

use App\Http\Controllers\Stores\StoreTerminalPaymentIntentController;
use App\Models\Store;
use Illuminate\Http\Request;

it('returns 422 when terminal payment intent amount is below Stripe minimum for NOK', function (): void {
    config(['cashier.secret' => 'sk_test_placeholder']);

    $store = new class extends Store
    {
        public function hasStripeAccount(): bool
        {
            return true;
        }
    };

    $store->forceFill(['stripe_account_id' => 'acct_test_minimum']);

    $request = Request::create('/ignored', 'POST', [
        'amount' => 299,
        'currency' => 'nok',
    ]);

    $response = app(StoreTerminalPaymentIntentController::class)($store, $request);

    expect($response->getStatusCode())->toBe(422);
    $payload = $response->getData(true);
    expect($payload['message'])->toBe('The payment amount is below the minimum Stripe allows for this currency.');
    expect($payload['errors']['amount'][0])
        ->toBe('The payment amount must be at least 3.00 NOK for terminal card payments.');
});
