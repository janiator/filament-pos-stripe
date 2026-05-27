<?php

use App\Actions\Stripe\SyncStoreStripeBalanceTransactionsFromStripe;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function fakeStripeBalanceTransaction(string $id, int $created = 1_700_000_000): object
{
    return (object) [
        'id' => $id,
        'type' => 'charge',
        'amount' => 10_000,
        'fee' => 300,
        'net' => 9_700,
        'currency' => 'nok',
        'status' => 'available',
        'description' => 'Test charge',
        'created' => $created,
        'available_on' => $created,
        'reporting_category' => 'charge',
        'fee_details' => [],
        'source' => (object) [
            'object' => 'charge',
            'id' => 'ch_test_'.$id,
            'metadata' => (object) [],
            'payment_intent' => null,
        ],
        'payout' => null,
    ];
}

it('upserts balance transactions in batches without per-row select queries', function (): void {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_batch_upsert']);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => 'acct_batch_upsert',
        'stripe_balance_transaction_id' => 'txn_existing',
        'type' => 'charge',
        'amount' => 5_000,
        'fee' => 0,
        'net' => 5_000,
        'currency' => 'nok',
        'stripe_created' => 1_600_000_000,
    ]);

    $action = new SyncStoreStripeBalanceTransactionsFromStripe;

    DB::flushQueryLog();
    DB::enableQueryLog();

    $result = $action->applyBalanceTransactions(
        $store,
        'acct_batch_upsert',
        [
            fakeStripeBalanceTransaction('txn_existing', 1_600_000_000),
            fakeStripeBalanceTransaction('txn_new', 1_700_000_001),
        ],
    );

    $selectQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => str_contains($sql, 'store_stripe_balance_transactions')
            && str_starts_with(strtolower(trim($sql)), 'select'));

    expect($selectQueries)->toHaveCount(1)
        ->and($result)->toMatchArray([
            'total' => 2,
            'created' => 1,
            'updated' => 1,
            'errors' => [],
        ]);

    expect(StoreStripeBalanceTransaction::query()->where('store_id', $store->id)->count())->toBe(2);

    $updated = StoreStripeBalanceTransaction::query()
        ->where('stripe_balance_transaction_id', 'txn_existing')
        ->first();

    expect($updated)->not->toBeNull()
        ->and($updated->amount)->toBe(10_000);
});

it('uses incremental stripe created filter when store already has mirrored rows', function (): void {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_incremental']);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => 'acct_incremental',
        'stripe_balance_transaction_id' => 'txn_old',
        'type' => 'charge',
        'amount' => 100,
        'fee' => 0,
        'net' => 100,
        'currency' => 'nok',
        'stripe_created' => 1_000_000_000,
    ]);

    $action = new SyncStoreStripeBalanceTransactionsFromStripe;
    $ref = new ReflectionClass($action);
    $method = $ref->getMethod('listParamsForStore');
    $method->setAccessible(true);

    $params = $method->invoke($action, $store, null);

    $overlap = (int) config('stripe_sync.balance_transactions.incremental_overlap_seconds', 86_400);

    expect($params)->toHaveKey('created')
        ->and($params['created']['gte'])->toBe(1_000_000_000 - $overlap);
});

it('does not apply incremental filter for payout-scoped sync', function (): void {
    $store = Store::factory()->create(['stripe_account_id' => 'acct_payout_scope']);

    StoreStripeBalanceTransaction::query()->create([
        'store_id' => $store->id,
        'stripe_account_id' => 'acct_payout_scope',
        'stripe_balance_transaction_id' => 'txn_old',
        'type' => 'charge',
        'amount' => 100,
        'fee' => 0,
        'net' => 100,
        'currency' => 'nok',
        'stripe_created' => 1_000_000_000,
    ]);

    $action = new SyncStoreStripeBalanceTransactionsFromStripe;
    $ref = new ReflectionClass($action);
    $method = $ref->getMethod('listParamsForStore');
    $method->setAccessible(true);

    $params = $method->invoke($action, $store, 'po_test_payout');

    expect($params)->toHaveKey('payout', 'po_test_payout')
        ->not->toHaveKey('created');
});
