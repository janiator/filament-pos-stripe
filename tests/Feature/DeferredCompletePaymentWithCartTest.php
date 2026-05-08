<?php

use App\Models\Addon;
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

/**
 * @return array{user: User, store: Store, session: PosSession, product: ConnectedProduct, variant: ProductVariant}
 */
function deferredCartTestSetup(): array
{
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_defcart_'.uniqid()]);
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

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'Cash',
        'code' => 'cash',
        'provider' => 'cash',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 1,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '10000',
        'saf_t_event_code' => '13016',
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
        'sku' => 'DEFCART-'.uniqid(),
        'inventory_quantity' => 10,
        'inventory_policy' => 'deny',
        'price_amount' => 10000,
        'currency' => 'nok',
        'active' => true,
    ]);

    return compact('user', 'store', 'session', 'product', 'variant');
}

test('complete deferred payment with revised cart updates charge amount and restores stock when qty decreases', function () {
    extract(deferredCartTestSetup());
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

    $completeResponse = $this->postJson("/api/purchases/{$chargeId}/complete-payment", [
        'payment_method_code' => 'cash',
        'pos_session_id' => $session->id,
        'metadata' => [],
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

    $completeResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.charge.amount', 10000);

    expect($completeResponse->json('data.receipt.id'))->toBeInt()->toBeGreaterThan(0);
    expect($completeResponse->json('receipt_id'))->toBe($completeResponse->json('data.receipt.id'));

    $variant->refresh();
    expect((float) $variant->inventory_quantity)->toEqual(9.0);

    expect(
        InventoryStockMovement::query()
            ->where('idempotency_key', 'deferred_complete_restore:charge:'.$chargeId.':variant:'.$variant->id)
            ->exists()
    )->toBeTrue();
});

test('complete deferred payment without cart keeps original amount', function () {
    extract(deferredCartTestSetup());

    Sanctum::actingAs($user, ['*']);

    $deferResponse = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'deferred',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 15000,
                ],
            ],
            'total' => 15000,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $deferResponse->assertCreated();
    $chargeId = $deferResponse->json('data.charge.id');

    $completeResponse = $this->postJson("/api/purchases/{$chargeId}/complete-payment", [
        'payment_method_code' => 'cash',
        'pos_session_id' => $session->id,
        'metadata' => [],
    ]);

    $completeResponse->assertOk()
        ->assertJsonPath('data.charge.amount', 15000);

    expect($completeResponse->json('data.receipt.id'))->toBeInt()->toBeGreaterThan(0);
});

test('complete deferred with cart increasing qty beyond available stock returns 422', function () {
    extract(deferredCartTestSetup());
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
                    'quantity' => 2,
                    'unit_price' => 10000,
                ],
            ],
            'total' => 20000,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $deferResponse->assertCreated();
    $chargeId = $deferResponse->json('data.charge.id');

    $variant->refresh();
    expect((float) $variant->inventory_quantity)->toEqual(8.0);

    $completeResponse = $this->postJson("/api/purchases/{$chargeId}/complete-payment", [
        'payment_method_code' => 'cash',
        'pos_session_id' => $session->id,
        'metadata' => [],
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 11,
                    'unit_price' => 10000,
                ],
            ],
            'total' => 110000,
            'currency' => 'nok',
        ],
    ]);

    $completeResponse->assertStatus(422)
        ->assertJsonPath('error', 'insufficient_stock');

    $variant->refresh();
    expect((float) $variant->inventory_quantity)->toEqual(8.0);
});

test('complete deferred with cart is rejected when charge is not a deferred purchase', function () {
    extract(deferredCartTestSetup());

    $charge = \App\Models\ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'amount' => 5000,
        'currency' => 'nok',
        'status' => 'pending',
        'paid' => false,
        'payment_method' => 'invoice',
        'metadata' => [
            'deferred_payment' => false,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 5000,
                ],
            ],
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson("/api/purchases/{$charge->id}/complete-payment", [
        'payment_method_code' => 'cash',
        'pos_session_id' => $session->id,
        'metadata' => [],
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 4000,
                ],
            ],
            'total' => 4000,
            'currency' => 'nok',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.cart.0', 'Cart revision is only allowed for deferred (pending) purchases');
});

test('complete deferred payment rejects deferred payment method code', function () {
    extract(deferredCartTestSetup());

    Sanctum::actingAs($user, ['*']);

    $deferResponse = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'deferred',
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

    $deferResponse->assertCreated();
    $chargeId = $deferResponse->json('data.charge.id');

    $response = $this->postJson("/api/purchases/{$chargeId}/complete-payment", [
        'payment_method_code' => 'deferred',
        'pos_session_id' => $session->id,
        'metadata' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method_code']);
});

test('GET purchase maps description to purchase_note when metadata has no note', function () {
    extract(deferredCartTestSetup());

    $charge = \App\Models\ConnectedCharge::factory()->create([
        'stripe_account_id' => $store->stripe_account_id,
        'pos_session_id' => $session->id,
        'amount' => 3000,
        'currency' => 'nok',
        'status' => 'pending',
        'paid' => false,
        'payment_method' => 'deferred',
        'description' => '  Pickup note from counter  ',
        'metadata' => [
            'deferred_payment' => true,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => 3000,
                ],
            ],
            'total' => 3000,
            'currency' => 'nok',
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/purchases/{$charge->id}")
        ->assertOk()
        ->assertJsonPath('purchase.purchase_note', 'Pickup note from counter');
});
