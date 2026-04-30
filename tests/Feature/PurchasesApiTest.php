<?php

use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

test('kiosk sales report includes mixed purchases but only kiosk item totals', function () {
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

    $mixedCharge = ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'paid' => true,
        'status' => 'succeeded',
        'amount' => 30000,
        'paid_at' => '2026-03-13 10:00:00',
        'metadata' => [
            'purchase_contains_tickets' => true,
            'purchase_ticket_reference' => 'BK-2026-000123',
            'total_discounts' => 2000,
            'items' => [
                [
                    'id' => 'ticket_line_1',
                    'name' => 'Billett',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'metadata' => [
                        'merano_booking_id' => 123,
                        'merano_booking_number' => 'BK-2026-000123',
                    ],
                ],
                [
                    'id' => 'kiosk_popcorn_1',
                    'name' => 'Popcorn',
                    'quantity' => 2,
                    'unit_price' => 5000,
                ],
                [
                    'id' => 'kiosk_soda_1',
                    'name' => 'Brus',
                    'quantity' => 1,
                    'unit_price' => 3000,
                ],
            ],
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
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.purchase_id', $kioskCharge->id);
    $response->assertJsonPath('data.0.net_amount_ore', 50000);
    $response->assertJsonPath('data.0.is_refund', false);
    $response->assertJsonPath('data.1.purchase_id', $mixedCharge->id);
    $response->assertJsonPath('data.1.net_amount_ore', 11869);
    $response->assertJsonPath('data.1.items.0.product_name', 'Popcorn');
    $response->assertJsonPath('data.1.items.0.line_total_ore', 9131);
    $response->assertJsonPath('data.1.items.1.product_name', 'Brus');
    $response->assertJsonPath('data.1.items.1.line_total_ore', 2738);
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

test('purchases index avoids repeated product lookup queries for metadata fallback enrichment', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_test_purchase_lookup_cache']);
    $user->stores()->attach($store);
    $user->setCurrentStore($store);

    $posDevice = PosDevice::factory()->create(['store_id' => $store->id]);
    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'pos_device_id' => $posDevice->id,
        'user_id' => $user->id,
        'status' => 'open',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'name' => 'Lookup cache product',
    ]);

    foreach (range(1, 3) as $index) {
        ConnectedCharge::factory()->create([
            'stripe_account_id' => $store->stripe_account_id,
            'pos_session_id' => $session->id,
            'paid' => true,
            'status' => 'succeeded',
            'amount' => 1000 * $index,
            'metadata' => [
                'items' => [
                    [
                        'id' => 'item_'.$index,
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit_price' => 1000,
                    ],
                ],
            ],
        ]);
    }

    Sanctum::actingAs($user, ['*']);

    $connectedProductQueries = 0;
    DB::listen(function ($query) use (&$connectedProductQueries) {
        if (str_contains($query->sql, 'from "connected_products"')) {
            $connectedProductQueries++;
        }
    });

    $response = $this->getJson('/api/purchases?per_page=20&page=0');

    $response->assertOk();
    expect($connectedProductQueries)->toBe(1);
});
