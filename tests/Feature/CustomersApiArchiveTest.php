<?php

use App\Models\ConnectedCustomer;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_customers_archive_test',
    ]);
    $this->user->stores()->attach($this->store);
    $this->user->setCurrentStore($this->store);
    Sanctum::actingAs($this->user, ['*']);
});

it('archives a customer via DELETE and returns archived_at', function (): void {
    $customer = ConnectedCustomer::create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'To Archive',
        'phone' => '+4712345678',
    ]);

    $response = $this->deleteJson("/api/customers/{$customer->id}");

    $response->assertSuccessful();
    $response->assertJsonPath('customer.id', $customer->id);
    expect($response->json('customer.archived_at'))->not->toBeNull();

    expect($customer->fresh()->archived_at)->not->toBeNull();
});

it('excludes archived customers from index by default', function (): void {
    $active = ConnectedCustomer::create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Active Customer',
        'phone' => '+4711111111',
    ]);
    $archived = ConnectedCustomer::create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived Customer',
        'phone' => '+4722222222',
        'archived_at' => now(),
    ]);

    $response = $this->getJson('/api/customers?per_page=100');

    $response->assertSuccessful();
    $ids = collect($response->json('customers'))->pluck('id');
    expect($ids)->toContain($active->id)
        ->and($ids)->not->toContain($archived->id);
});

it('lists archived customers when include_archived is true', function (): void {
    $archived = ConnectedCustomer::create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived Customer',
        'phone' => '+4722222222',
        'archived_at' => now(),
    ]);

    $response = $this->getJson('/api/customers?include_archived=1&per_page=100');

    $response->assertSuccessful();
    $ids = collect($response->json('customers'))->pluck('id');
    expect($ids)->toContain($archived->id);
});

it('rejects updates to an archived customer', function (): void {
    $customer = ConnectedCustomer::create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived Customer',
        'phone' => '+4722222222',
        'archived_at' => now(),
    ]);

    $response = $this->putJson("/api/customers/{$customer->id}", [
        'name' => 'Updated Name',
        'phone' => '+4733333333',
    ]);

    $response->assertUnprocessable();
});

it('is idempotent when deleting an already archived customer', function (): void {
    $customer = ConnectedCustomer::create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Already Archived',
        'phone' => '+4744444444',
        'archived_at' => now(),
    ]);

    $response = $this->deleteJson("/api/customers/{$customer->id}");

    $response->assertSuccessful();
    expect($customer->fresh()->archived_at)->not->toBeNull();
});
