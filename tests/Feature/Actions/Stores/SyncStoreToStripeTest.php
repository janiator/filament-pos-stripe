<?php

use App\Actions\Stores\SyncStoreToStripe;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    Log::spy();
});

it('skips update and logs warning when Stripe returns live-keys-only error', function () {
    $store = Store::factory()->create([
        'stripe_account_id' => 'acct_1QkisCRu3Ljbb32R',
    ]);

    $accountService = Mockery::mock();
    $accountService->shouldReceive('update')
        ->once()
        ->andThrow(new InvalidRequestException('Only live keys can access this method.'));

    $stripe = Mockery::mock(StripeClient::class);
    $stripe->accounts = $accountService;

    $action = new SyncStoreToStripe;
    $action($store, $stripe);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($store) {
            return str_contains($message, 'Skipping account update')
                && ($context['store_id'] ?? null) === $store->id
                && ($context['stripe_account_id'] ?? null) === $store->stripe_account_id;
        });
});
