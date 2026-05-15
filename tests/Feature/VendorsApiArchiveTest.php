<?php

use App\Models\ConnectedProduct;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_vendors_archive_test',
    ]);
    $this->user->stores()->attach($this->store);
    $this->user->setCurrentStore($this->store);
    Sanctum::actingAs($this->user, ['*']);
});

it('archives a vendor via DELETE and returns archived_at', function (): void {
    $vendor = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'To Archive',
        'active' => true,
    ]);

    $response = $this->deleteJson("/api/vendors/{$vendor->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('vendor.id', $vendor->id);
    expect($response->json('vendor.archived_at'))->not->toBeNull();

    expect($vendor->fresh()->archived_at)->not->toBeNull();
});

it('excludes archived vendors from index by default', function (): void {
    $active = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Active Vendor',
        'active' => true,
    ]);
    $archived = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived Vendor',
        'active' => true,
        'archived_at' => now(),
    ]);

    $response = $this->getJson('/api/vendors?per_page=100');

    $response->assertSuccessful();
    $ids = collect($response->json('vendors'))->pluck('id')->all();
    expect($ids)->toContain($active->id)
        ->not->toContain($archived->id);
});

it('lists archived vendors when include_archived is true', function (): void {
    $archived = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived Vendor',
        'active' => true,
        'archived_at' => now(),
    ]);

    $response = $this->getJson('/api/vendors?include_archived=1&per_page=100');

    $response->assertSuccessful();
    $ids = collect($response->json('vendors'))->pluck('id')->all();
    expect($ids)->toContain($archived->id);
});

it('rejects updates to an archived vendor', function (): void {
    $vendor = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived',
        'active' => true,
        'archived_at' => now(),
    ]);

    $this->putJson("/api/vendors/{$vendor->id}", [
        'name' => 'Should Fail',
    ])->assertStatus(422);
});

it('rejects assigning an archived vendor to a product', function (): void {
    $archivedVendor = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived',
        'active' => true,
        'archived_at' => now(),
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
    ]);

    $this->putJson("/api/products/{$product->id}", [
        'vendor_id' => $archivedVendor->id,
    ])->assertStatus(422);
});

it('is idempotent when deleting an already archived vendor', function (): void {
    $vendor = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Already Archived',
        'active' => true,
        'archived_at' => now(),
    ]);

    $this->deleteJson("/api/vendors/{$vendor->id}")
        ->assertSuccessful();
});
