<?php

use App\Actions\Stripe\SyncStoreStripeBalanceTransactionsFromStripe;

it('prepares nullable json columns for query builder upsert bindings', function (): void {
    $action = new SyncStoreStripeBalanceTransactionsFromStripe;
    $method = (new ReflectionClass($action))->getMethod('encodeNullableJsonArray');
    $method->setAccessible(true);

    expect($method->invoke($action, null))->toBeNull()
        ->and($method->invoke($action, []))->toBeNull()
        ->and($method->invoke($action, ['k' => 'v']))->toBe('{"k":"v"}');
});
