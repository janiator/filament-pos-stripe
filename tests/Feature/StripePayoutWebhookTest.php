<?php

declare(strict_types=1);

use App\Jobs\SyncStoreStripeBalanceTransactionsJob;
use App\Models\Store;
use App\Models\StoreStripePayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Lanos\CashierConnect\Models\ConnectMapping;

uses(RefreshDatabase::class);

it('creates store_stripe_payouts from a signed Connect payout.paid webhook', function (): void {
    $secret = 'whsec_payout_test_'.uniqid();
    config()->set('cashierconnect.webhook.secret', $secret);

    $accountId = 'acct_po_'.uniqid();
    $store = Store::factory()->create(['stripe_account_id' => $accountId]);

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $accountId,
        'type' => 'standard',
    ]);

    $payoutId = 'po_'.uniqid();
    $arrival = strtotime('+1 day');
    $created = time();

    $payload = json_encode([
        'id' => 'evt_'.uniqid(),
        'object' => 'event',
        'type' => 'payout.paid',
        'account' => $accountId,
        'data' => [
            'object' => [
                'id' => $payoutId,
                'object' => 'payout',
                'amount' => 12_345,
                'currency' => 'nok',
                'status' => 'paid',
                'arrival_date' => $arrival,
                'created' => $created,
                'automatic' => true,
                'method' => 'standard',
                'metadata' => [],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);
    $signatureHeader = "t={$timestamp},v1={$signature}";

    $response = $this->withHeader('Stripe-Signature', $signatureHeader)
        ->postJson('/api/connectWebhook', json_decode($payload, true, 512, JSON_THROW_ON_ERROR));

    $response->assertOk()
        ->assertJsonPath('processed', true)
        ->assertJsonPath('event_type', 'payout.paid');

    $row = StoreStripePayout::query()->where('stripe_payout_id', $payoutId)->first();
    expect($row)->not->toBeNull()
        ->and($row->store_id)->toBe($store->id)
        ->and($row->stripe_account_id)->toBe($accountId)
        ->and($row->amount)->toBe(12_345)
        ->and($row->currency)->toBe('nok')
        ->and($row->status)->toBe('paid')
        ->and($row->method)->toBe('standard')
        ->and($row->stripe_created)->toBe($created);
});

it('updates an existing payout row on payout.updated webhook', function (): void {
    $secret = 'whsec_payout_update_'.uniqid();
    config()->set('cashierconnect.webhook.secret', $secret);

    $accountId = 'acct_po_upd_'.uniqid();
    $store = Store::factory()->create(['stripe_account_id' => $accountId]);

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $accountId,
        'type' => 'standard',
    ]);

    $payoutId = 'po_'.uniqid();
    $existing = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => $accountId,
        'stripe_payout_id' => $payoutId,
        'amount' => 1000,
        'currency' => 'nok',
        'status' => 'pending',
        'arrival_date' => null,
        'method' => 'standard',
        'failure_code' => null,
        'failure_message' => null,
        'statement_descriptor' => null,
        'automatic' => true,
        'stripe_created' => time() - 3600,
        'metadata' => null,
    ]));

    $arrival = strtotime('+2 days');
    $created = (int) $existing->stripe_created;

    $payload = json_encode([
        'id' => 'evt_'.uniqid(),
        'object' => 'event',
        'type' => 'payout.updated',
        'account' => $accountId,
        'data' => [
            'object' => [
                'id' => $payoutId,
                'object' => 'payout',
                'amount' => 9999,
                'currency' => 'nok',
                'status' => 'in_transit',
                'arrival_date' => $arrival,
                'created' => $created,
                'automatic' => true,
                'method' => 'standard',
                'metadata' => [],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);
    $signatureHeader = "t={$timestamp},v1={$signature}";

    $this->withHeader('Stripe-Signature', $signatureHeader)
        ->postJson('/api/connectWebhook', json_decode($payload, true, 512, JSON_THROW_ON_ERROR))
        ->assertOk()
        ->assertJsonPath('processed', true);

    $existing->refresh();
    expect($existing->amount)->toBe(9999)
        ->and($existing->status)->toBe('in_transit')
        ->and($existing->arrival_date)->not->toBeNull();
});

it('queues payout-scoped balance transaction sync on payout.paid webhook', function (): void {
    Queue::fake();

    $secret = 'whsec_payout_bt_'.uniqid();
    config()->set('cashierconnect.webhook.secret', $secret);

    $accountId = 'acct_po_bt_'.uniqid();
    $store = Store::factory()->create(['stripe_account_id' => $accountId]);

    ConnectMapping::query()->create([
        'model' => Store::class,
        'model_id' => $store->id,
        'stripe_account_id' => $accountId,
        'type' => 'standard',
    ]);

    $payoutId = 'po_'.uniqid();
    $arrival = strtotime('+1 day');
    $created = time();

    $payload = json_encode([
        'id' => 'evt_'.uniqid(),
        'object' => 'event',
        'type' => 'payout.paid',
        'account' => $accountId,
        'data' => [
            'object' => [
                'id' => $payoutId,
                'object' => 'payout',
                'amount' => 5000,
                'currency' => 'nok',
                'status' => 'paid',
                'arrival_date' => $arrival,
                'created' => $created,
                'automatic' => true,
                'method' => 'standard',
                'metadata' => [],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);
    $signatureHeader = "t={$timestamp},v1={$signature}";

    $this->withHeader('Stripe-Signature', $signatureHeader)
        ->postJson('/api/connectWebhook', json_decode($payload, true, 512, JSON_THROW_ON_ERROR))
        ->assertOk();

    Queue::assertPushed(SyncStoreStripeBalanceTransactionsJob::class, function (SyncStoreStripeBalanceTransactionsJob $job) use ($store, $payoutId): bool {
        return (int) $job->store->getKey() === (int) $store->getKey()
            && $job->onlyStripePayoutId === $payoutId;
    });
});
