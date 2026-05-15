<?php

declare(strict_types=1);

use App\Filament\Resources\StoreStripeBalanceTransactions\StoreStripeBalanceTransactionResource;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $this->storeA = Store::factory()->create([
        'slug' => 'store-a-'.fake()->unique()->numerify('####'),
        'stripe_account_id' => 'acct_a_'.fake()->uuid(),
    ]);
    $this->storeB = Store::factory()->create([
        'slug' => 'store-b-'.fake()->unique()->numerify('####'),
        'stripe_account_id' => 'acct_b_'.fake()->uuid(),
    ]);

    $this->admin = User::factory()->create();
    $this->admin->stores()->attach([$this->storeA->id, $this->storeB->id]);
    $this->admin->assignRole('super_admin');

    $this->rowA = StoreStripeBalanceTransaction::query()->create([
        'store_id' => $this->storeA->id,
        'stripe_account_id' => $this->storeA->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_a_'.fake()->uuid(),
        'type' => 'charge',
        'amount' => 100,
        'fee' => 10,
        'net' => 90,
        'currency' => 'nok',
        'status' => 'available',
    ]);
    $this->rowB = StoreStripeBalanceTransaction::query()->create([
        'store_id' => $this->storeB->id,
        'stripe_account_id' => $this->storeB->stripe_account_id,
        'stripe_balance_transaction_id' => 'txn_b_'.fake()->uuid(),
        'type' => 'charge',
        'amount' => 200,
        'fee' => 20,
        'net' => 180,
        'currency' => 'nok',
        'status' => 'available',
    ]);
});

it('scopes balance transactions to the current tenant store', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->storeA);
    Filament::bootCurrentPanel();

    $ids = StoreStripeBalanceTransactionResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($this->rowA->id)
        ->not->toContain($this->rowB->id);
});
