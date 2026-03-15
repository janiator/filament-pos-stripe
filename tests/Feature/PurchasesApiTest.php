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

test('single purchase persists and returns cart and item discounts', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_discounts']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Cash',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '10000',
        'saf_t_event_code' => '13016',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
    ]);

    Sanctum::actingAs($user, ['*']);

    $createResponse = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'discount_amount' => 1500,
                    'discount_reason' => 'Manual item discount',
                ],
            ],
            'discounts' => [
                [
                    'type' => 'verdi',
                    'amount' => 500,
                    'reason' => 'Loyalty',
                ],
            ],
            'subtotal' => 10000,
            'total_discounts' => 2000,
            'total_tax' => 1600,
            'total' => 8000,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $createResponse->assertCreated();
    $purchaseId = $createResponse->json('data.charge.id');

    $showResponse = $this->getJson("/api/purchases/{$purchaseId}");

    $showResponse->assertOk()
        ->assertJsonPath('purchase.purchase_discounts.0.type', 'verdi')
        ->assertJsonPath('purchase.purchase_discounts.0.amount', 500)
        ->assertJsonPath('purchase.purchase_total_discounts', 2000)
        ->assertJsonPath('purchase.purchase_items.0.purchase_item_discount_amount', 1500)
        ->assertJsonPath('purchase.purchase_items.0.purchase_item_discount_reason', 'Manual item discount');
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

test('kiosk sales report returns only kiosk purchases in date range', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_kiosk_report']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    $kioskCharge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'paid' => true,
        'status' => 'succeeded',
        'amount' => 50000,
        'amount_refunded' => 0,
        'paid_at' => '2026-03-13 09:30:00',
        'metadata' => [
            'items' => [
                [
                    'id' => 'item_kiosk_1',
                    'name' => 'Kaffe',
                    'quantity' => 2,
                    'unit_price' => 25000,
                ],
            ],
        ],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'paid' => true,
        'status' => 'succeeded',
        'amount' => 30000,
        'paid_at' => '2026-03-13 10:00:00',
        'metadata' => [
            'purchase_contains_tickets' => true,
            'purchase_ticket_reference' => 'BK-2026-000123',
        ],
    ]);

    ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'paid' => true,
        'status' => 'succeeded',
        'amount' => 15000,
        'paid_at' => '2026-03-12 23:30:00',
        'metadata' => [],
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/reports/kiosk-sales?from_datetime=2026-03-13T00:00:00Z&to_datetime=2026-03-13T23:59:59Z&limit=50');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.purchase_id', $kioskCharge->id);
    $response->assertJsonPath('data.0.net_amount_ore', 50000);
    $response->assertJsonPath('data.0.is_refund', false);
});

test('kiosk sales report supports cursor and updated_since filters', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_kiosk_cursor']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    $olderKioskCharge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'paid' => true,
        'status' => 'succeeded',
        'amount' => 10000,
        'paid_at' => '2026-03-13 08:00:00',
        'updated_at' => '2026-03-13 08:10:00',
        'metadata' => [],
    ]);

    $newerKioskCharge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'paid' => true,
        'status' => 'succeeded',
        'amount' => 20000,
        'amount_refunded' => 5000,
        'paid_at' => '2026-03-13 09:00:00',
        'updated_at' => '2026-03-13 09:30:00',
        'metadata' => [],
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson(sprintf(
        '/api/reports/kiosk-sales?from_datetime=2026-03-13T00:00:00Z&to_datetime=2026-03-13T23:59:59Z&cursor=%d&updated_since=2026-03-13T09:00:00Z&limit=50',
        $olderKioskCharge->id
    ));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.purchase_id', $newerKioskCharge->id);
    $response->assertJsonPath('data.0.net_amount_ore', 15000);
    $response->assertJsonPath('data.0.is_refund', true);
    $response->assertJsonPath('meta.cursor', $olderKioskCharge->id);
});
