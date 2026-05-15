<?php

declare(strict_types=1);

use App\Filament\Resources\StoreStripePayouts\StoreStripePayoutResource;
use App\Models\Store;
use App\Models\StoreStripePayout;
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

    $this->payoutA = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $this->storeA->id,
        'stripe_account_id' => $this->storeA->stripe_account_id,
        'stripe_payout_id' => 'po_test_a_'.fake()->uuid(),
        'amount' => 1000,
        'currency' => 'nok',
        'status' => 'paid',
    ]));

    $this->payoutB = StoreStripePayout::withoutEvents(fn (): StoreStripePayout => StoreStripePayout::query()->create([
        'store_id' => $this->storeB->id,
        'stripe_account_id' => $this->storeB->stripe_account_id,
        'stripe_payout_id' => 'po_test_b_'.fake()->uuid(),
        'amount' => 2000,
        'currency' => 'nok',
        'status' => 'paid',
    ]));
});

it('scopes the store stripe payouts resource query to the current tenant store', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->storeA);
    Filament::bootCurrentPanel();

    $ids = StoreStripePayoutResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($this->payoutA->id)
        ->not->toContain($this->payoutB->id);
});

it('scopes to the other store when the tenant is switched', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->storeB);
    Filament::bootCurrentPanel();

    $ids = StoreStripePayoutResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($this->payoutB->id)
        ->not->toContain($this->payoutA->id);
});
