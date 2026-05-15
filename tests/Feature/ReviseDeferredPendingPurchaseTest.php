<?php

use App\Models\Addon;
use App\Models\ConnectedCharge;
use App\Models\ConnectedPrice;
use App\Models\ConnectedProduct;
use App\Models\InventoryStockMovement;
use App\Models\PaymentMethod;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('revise-deferred updates pending charge amount and restores stock when qty decreases', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_revdef_'.uniqid()]);
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
        'name' => 'Betaling ved henting',
        'code' => 'deferred',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 0,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12010',
        'saf_t_event_code' => '13019',
    ]);

    $product = ConnectedProduct::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'track_inventory' => true,
    ]);

    $price = ConnectedPrice::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_product_id' => $product->stripe_product_id,
        'unit_amount' => 10000,
    ]);

    $variant = ProductVariant::query()->create([
        'connected_product_id' => $product->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_price_id' => $price->stripe_price_id,
        'sku' => 'REVDEF-'.uniqid(),
        'inventory_quantity' => 10,
        'inventory_policy' => 'deny',
        'price_amount' => 10000,
        'currency' => 'nok',
        'active' => true,
    ]);

    Addon::factory()->inventory()->create(['store_id' => $store->id]);

    Sanctum::actingAs($user, ['*']);

    $deferResponse = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'deferred',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 3,
                    'unit_price' => 10000,
                ],
            ],
            'total' => 30000,
            'currency' => 'nok',
        ],
        'metadata' => [
            'deferred_reason' => 'Pickup later',
        ],
    ]);

    $deferResponse->assertCreated();
    $chargeId = $deferResponse->json('data.charge.id');

    $variant->refresh();
    expect((float) $variant->inventory_quantity)->toEqual(7.0);

    $reviseResponse = $this->postJson("/api/purchases/{$chargeId}/revise-deferred", [
        'pos_session_id' => $session->id,
        'metadata' => [
            'estimated_pickup_date' => '2026-06-01T12:00:00Z',
        ],
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ],
            'total' => 10000,
            'currency' => 'nok',
        ],
    ]);

    $reviseResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.charge.amount', 10000)
        ->assertJsonPath('data.charge.status', 'pending');

    $variant->refresh();
    expect((float) $variant->inventory_quantity)->toEqual(9.0);

    expect(
        InventoryStockMovement::query()
            ->where('idempotency_key', 'deferred_complete_restore:charge:'.$chargeId.':variant:'.$variant->id)
            ->exists()
    )->toBeTrue();

    $charge = ConnectedCharge::findOrFail($chargeId);
    $meta = is_array($charge->metadata) ? $charge->metadata : [];
    expect($meta['estimated_pickup_date'])->toBe('2026-06-01T12:00:00Z');
});

test('revise-deferred returns 422 when charge is not a deferred pending purchase', function () {
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_revdef2_'.uniqid()]);
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

    $price = ConnectedPrice::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_product_id' => $product->stripe_product_id,
        'unit_amount' => 5000,
    ]);

    $variant = ProductVariant::query()->create([
        'connected_product_id' => $product->id,
        'stripe_account_id' => $store->stripe_account_id,
        'stripe_price_id' => $price->stripe_price_id,
        'sku' => 'REVDEF2-'.uniqid(),
        'inventory_quantity' => null,
        'inventory_policy' => 'continue',
        'price_amount' => 5000,
        'currency' => 'nok',
        'active' => true,
    ]);

    Sanctum::actingAs($user, ['*']);

    $paid = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 5000,
                ],
            ],
            'total' => 5000,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $paid->assertSuccessful();
    $chargeId = $paid->json('data.charge.id');

    $this->postJson("/api/purchases/{$chargeId}/revise-deferred", [
        'pos_session_id' => $session->id,
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => 5000,
                ],
            ],
            'total' => 10000,
            'currency' => 'nok',
        ],
    ])->assertStatus(422);
});
