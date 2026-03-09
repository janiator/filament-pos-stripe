<?php

use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosEvent;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use App\Services\CashDrawerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.str_replace('-', '', fake()->uuid),
    ]);
    $this->user = User::factory()->create();
    $this->user->stores()->attach($this->store);
    $this->user->setCurrentStore($this->store);
    Sanctum::actingAs($this->user, ['*']);
});

test('single purchase with cash on drawer-disabled device returns 422', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Cash',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'saf_t_payment_code' => '12001',
        'saf_t_event_code' => '13016',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'article_group_code' => '04003',
    ]);
    $total = 5000;

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $total,
                    'description' => $product->name,
                ],
            ],
            'total' => $total,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Cash payments are not allowed on this device (cash drawer disabled).');
});

test('single purchase with non-cash on drawer-disabled device succeeds', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Vipps',
        'code' => 'vipps',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 1,
        'saf_t_payment_code' => '12011',
        'saf_t_event_code' => '13018',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'article_group_code' => '04003',
    ]);
    $total = 5000;

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'vipps',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $total,
                    'description' => $product->name,
                ],
            ],
            'total' => $total,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
});

test('split purchase including cash on drawer-disabled device returns 422', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Cash',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'saf_t_payment_code' => '12001',
        'saf_t_event_code' => '13016',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Vipps',
        'code' => 'vipps',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 1,
        'saf_t_payment_code' => '12011',
        'saf_t_event_code' => '13018',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'article_group_code' => '04003',
    ]);
    $total = 5000;

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payments' => [
            ['payment_method_code' => 'cash', 'amount' => 2500, 'metadata' => []],
            ['payment_method_code' => 'vipps', 'amount' => 2500, 'metadata' => []],
        ],
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $total,
                    'description' => $product->name,
                ],
            ],
            'total' => $total,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Cash payments are not allowed on this device (cash drawer disabled).');
});

test('split purchase with only non-cash on drawer-disabled device succeeds', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Vipps',
        'code' => 'vipps',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 1,
        'saf_t_payment_code' => '12011',
        'saf_t_event_code' => '13018',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'article_group_code' => '04003',
    ]);
    $total = 5000;

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payments' => [
            ['payment_method_code' => 'vipps', 'amount' => $total, 'metadata' => []],
        ],
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $total,
                    'description' => $product->name,
                ],
            ],
            'total' => $total,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
});

test('complete deferred payment with cash on drawer-disabled device returns 422', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Cash',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'saf_t_payment_code' => '12001',
        'saf_t_event_code' => '13016',
    ]);
    $charge = \App\Models\ConnectedCharge::create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'store_id' => $this->store->id,
        'pos_session_id' => $session->id,
        'amount' => 5000,
        'currency' => 'nok',
        'status' => 'pending',
        'paid' => false,
        'payment_method' => 'deferred',
        'metadata' => ['items' => []],
    ]);

    $response = $this->postJson("/api/purchases/{$charge->id}/complete-payment", [
        'payment_method_code' => 'cash',
        'pos_session_id' => $session->id,
        'metadata' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Cash payments are not allowed on this device (cash drawer disabled).');
});

test('cash drawer open API returns 403 for drawer-disabled device', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);

    $response = $this->postJson("/api/pos-devices/{$device->id}/cash-drawer/open", []);

    $response->assertStatus(403);
    $response->assertJsonPath('message', 'Cash drawer is disabled for this device.');
    $this->assertDatabaseMissing('pos_events', [
        'pos_device_id' => $device->id,
        'event_code' => PosEvent::EVENT_CASH_DRAWER_OPEN,
    ]);
});

test('cash drawer close API returns 403 for drawer-disabled device', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);

    $response = $this->postJson("/api/pos-devices/{$device->id}/cash-drawer/close", []);

    $response->assertStatus(403);
    $response->assertJsonPath('message', 'Cash drawer is disabled for this device.');
});

test('payment methods exclude cash when pos_device_id has drawer disabled', function () {
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Cash',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'saf_t_payment_code' => '12001',
        'saf_t_event_code' => '13016',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Vipps',
        'code' => 'vipps',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 1,
        'saf_t_payment_code' => '12011',
        'saf_t_event_code' => '13018',
    ]);
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);

    $response = $this->getJson('/api/purchases/payment-methods?pos_device_id='.$device->id);

    $response->assertStatus(200);
    $data = $response->json('data');
    $codes = array_column($data, 'code');
    expect($codes)->not->toContain('cash');
});

test('device response includes cash_drawer_enabled', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);

    $response = $this->getJson("/api/pos-devices/{$device->id}");

    $response->assertStatus(200);
    $response->assertJsonPath('device.cash_drawer_enabled', false);
});

test('CashDrawerService does not create event when device has cash drawer disabled', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => false,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    $session->setRelation('posDevice', $device);

    $service = app(CashDrawerService::class);
    $service->openCashDrawer($session, 5000);

    $this->assertDatabaseMissing('pos_events', [
        'pos_device_id' => $device->id,
        'event_code' => PosEvent::EVENT_CASH_DRAWER_OPEN,
    ]);
});
