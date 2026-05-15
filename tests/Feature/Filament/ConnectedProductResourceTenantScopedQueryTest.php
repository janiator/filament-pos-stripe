<?php

declare(strict_types=1);

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use App\Models\ConnectedProduct;
use App\Models\Store;
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

    $this->productA = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->storeA->stripe_account_id,
    ]);
    $this->productB = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->storeB->stripe_account_id,
    ]);
});

it('scopes the connected products resource query to the current tenant Stripe account', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->storeA);
    Filament::bootCurrentPanel();

    $ids = ConnectedProductResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($this->productA->id)
        ->not->toContain($this->productB->id);
});

it('scopes to the other store when the tenant is switched', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->storeB);
    Filament::bootCurrentPanel();

    $ids = ConnectedProductResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($this->productB->id)
        ->not->toContain($this->productA->id);
});
