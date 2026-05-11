<?php

use App\Actions\Stripe\SyncStoreStripeBalanceTransactionsFromStripe;
use App\Jobs\SyncStoreStripeBalanceTransactionsJob;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches sync store stripe balance transactions job', function (): void {
    Queue::fake();

    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_sync_bt']);

    SyncStoreStripeBalanceTransactionsJob::dispatch($store);

    Queue::assertPushed(SyncStoreStripeBalanceTransactionsJob::class, function (SyncStoreStripeBalanceTransactionsJob $job) use ($store): bool {
        return (int) $job->store->getKey() === (int) $store->getKey();
    });
});

it('invokes balance transaction sync action when job runs', function (): void {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_sync_bt_run']);

    $sync = \Mockery::mock(SyncStoreStripeBalanceTransactionsFromStripe::class);
    $sync->shouldReceive('__invoke')
        ->once()
        ->with(\Mockery::on(fn (Store $s): bool => (int) $s->getKey() === (int) $store->getKey()), false, null)
        ->andReturn(['total' => 3, 'created' => 0, 'updated' => 3, 'errors' => []]);

    $job = new SyncStoreStripeBalanceTransactionsJob($store);
    $job->handle($sync);
});

it('passes stripe payout id to balance transaction sync when job is payout-scoped', function (): void {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_sync_bt_payout']);

    $sync = \Mockery::mock(SyncStoreStripeBalanceTransactionsFromStripe::class);
    $sync->shouldReceive('__invoke')
        ->once()
        ->with(\Mockery::on(fn (Store $s): bool => (int) $s->getKey() === (int) $store->getKey()), false, 'po_scope_test')
        ->andReturn(['total' => 5, 'created' => 1, 'updated' => 4, 'errors' => []]);

    $job = new SyncStoreStripeBalanceTransactionsJob($store, 'po_scope_test');
    $job->handle($sync);
});

it('skips sync when store has no stripe account id', function (): void {
    $store = Store::factory()->create(['stripe_account_id' => null]);

    $sync = \Mockery::mock(SyncStoreStripeBalanceTransactionsFromStripe::class);
    $sync->shouldNotReceive('__invoke');

    $job = new SyncStoreStripeBalanceTransactionsJob($store);
    $job->handle($sync);
});
