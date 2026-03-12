<?php

use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('single purchase with cart total 0 (freeticket) is accepted when payment method has no minimum', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_freeticket']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id, 'cash_drawer_enabled' => false]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);
    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Gratis',
        'code' => 'free',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12000',
        'saf_t_event_code' => '13018',
    ]);
    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'article_group_code' => '04003',
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'free',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 0,
                    'description' => 'Gratisbillett',
                ],
            ],
            'total' => 0,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.charge.amount', 0);
});

test('get purchase returns purchase item quantities with decimals', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_decimal']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
    ]);

    $charge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'status' => 'succeeded',
        'metadata' => [
            'items' => [
                [
                    'id' => 'item_1',
                    'product_id' => '1',
                    'product_name' => 'Test Product',
                    'quantity' => 2.5,
                    'unit_price' => 1000,
                    'discount_amount' => 0,
                ],
            ],
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/purchases/{$charge->id}");

    $response->assertOk();
    $response->assertJsonPath('purchase.purchase_items.0.purchase_item_quantity', 2.5);
});
