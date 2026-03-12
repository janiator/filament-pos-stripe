<?php

use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
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

test('single purchase below payment method minimum returns 422', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => true,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Kort',
        'code' => 'card_present',
        'provider' => 'stripe',
        'provider_method' => 'card_present',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => 100,
        'saf_t_payment_code' => '12002',
        'saf_t_event_code' => '13017',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'article_group_code' => '04003',
    ]);
    $totalOre = 5000; // 50 kr

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'card_present',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $totalOre,
                    'description' => $product->name,
                ],
            ],
            'total' => $totalOre,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('message', 'Minimum amount for this payment method is 100 kr.');
});

test('single purchase at or above payment method minimum is accepted', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => true,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Kontant',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => 50,
        'saf_t_payment_code' => '12001',
        'saf_t_event_code' => '13016',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'article_group_code' => '04003',
    ]);
    $totalOre = 5000; // 50 kr, exactly at minimum

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $totalOre,
                    'description' => $product->name,
                ],
            ],
            'total' => $totalOre,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
});

test('payment method with null minimum has no minimum enforced', function () {
    $device = PosDevice::factory()->create([
        'store_id' => $this->store->id,
        'cash_drawer_enabled' => true,
    ]);
    $session = PosSession::factory()->create([
        'store_id' => $this->store->id,
        'pos_device_id' => $device->id,
        'user_id' => $this->user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Kontant',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12001',
        'saf_t_event_code' => '13016',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'article_group_code' => '04003',
    ]);
    $totalOre = 100; // 1 kr

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $totalOre,
                    'description' => $product->name,
                ],
            ],
            'total' => $totalOre,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
});

test('get payment methods returns minimum_amount_ore', function () {
    PaymentMethod::create([
        'store_id' => $this->store->id,
        'name' => 'Kort',
        'code' => 'card_present',
        'provider' => 'stripe',
        'provider_method' => 'card_present',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => 75,
        'saf_t_payment_code' => '12002',
        'saf_t_event_code' => '13017',
    ]);

    $response = $this->getJson('/api/purchases/payment-methods');

    $response->assertOk();
    $response->assertJsonPath('data.0.minimum_amount_ore', 7500);
    $response->assertJsonMissingPath('data.0.minimum_amount_kroner');
});
