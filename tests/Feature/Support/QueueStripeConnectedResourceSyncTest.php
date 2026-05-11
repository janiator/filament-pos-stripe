<?php

use App\Jobs\SyncStoreChargesFromStripeJob;
use App\Models\Store;
use App\Support\Filament\QueueStripeConnectedResourceSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('queues one charge sync job when a single store is targeted', function (): void {
    Queue::fake();

    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_single']);

    $count = QueueStripeConnectedResourceSync::dispatch(
        'Sync charges from Stripe',
        'charges',
        fn (Store $store): SyncStoreChargesFromStripeJob => new SyncStoreChargesFromStripeJob($store),
        collect([$store]),
    );

    expect($count)->toBe(1);
    Queue::assertPushed(SyncStoreChargesFromStripeJob::class, 1);
});

it('dispatches a bus batch when multiple stores are targeted', function (): void {
    Bus::fake();

    $a = Store::factory()->create(['stripe_account_id' => 'acct_test_a']);
    $b = Store::factory()->create(['stripe_account_id' => 'acct_test_b']);

    $count = QueueStripeConnectedResourceSync::dispatch(
        'Sync charges from Stripe',
        'charges',
        fn (Store $store): SyncStoreChargesFromStripeJob => new SyncStoreChargesFromStripeJob($store),
        collect([$a, $b]),
    );

    expect($count)->toBe(2);
    Bus::assertBatched(fn ($batch): bool => $batch->name === 'Sync charges from Stripe'
        && $batch->jobs->count() === 2);
});

it('returns zero when no eligible stores are given', function (): void {
    Queue::fake();
    Bus::fake();

    $count = QueueStripeConnectedResourceSync::dispatch(
        'Sync charges from Stripe',
        'charges',
        fn (Store $store): SyncStoreChargesFromStripeJob => new SyncStoreChargesFromStripeJob($store),
        collect([]),
    );

    expect($count)->toBe(0);
    Queue::assertNothingPushed();
    Bus::assertNothingBatched();
});
