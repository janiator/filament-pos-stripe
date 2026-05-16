<?php

use App\Models\ConnectedCustomer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('connected customer persists ten digit phone numbers that exceed 32-bit integer range', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_phone']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/customers', [
        'name' => 'Phone Overflow Test',
        'email' => 'phone-overflow-test@example.com',
        'phone' => '9545559600',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('phone', '9545559600');

    $customerId = $response->json('id');

    $this->assertDatabaseHas('stripe_connected_customer_mappings', [
        'id' => $customerId,
        'phone' => '9545559600',
    ]);
});

test('stripe_connected_customer_mappings phone column is not a 32-bit integer on pgsql', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only: verifies migrated phone column type.');
    }

    $row = DB::selectOne(
        "select data_type from information_schema.columns where table_schema = current_schema() and table_name = 'stripe_connected_customer_mappings' and column_name = 'phone'"
    );

    expect($row)->not->toBeNull();
    expect($row->data_type)->not->toBe('integer')
        ->and($row->data_type)->toBeIn(['character varying', 'text']);
});

test('connected customer model accepts numeric phone input from request as string storage', function () {
    $customer = ConnectedCustomer::withoutEvents(function () {
        return ConnectedCustomer::query()->create([
            'stripe_account_id' => 'acct_numeric_phone',
            'stripe_customer_id' => 'cus_numeric_phone',
            'name' => 'Numeric phone',
            'email' => null,
            'phone' => 9545559600,
        ]);
    });

    expect($customer->fresh()->phone)->toBe('9545559600');
});
