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

function inventoryTestContext(): array
{
    $user = User::factory()->create();
    $store = Store::factory()->create(['stripe_account_id' => 'acct_inv_'.uniqid()]);
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

    PaymentMethod::create([
        'store_id' => $store->id,
        'name' => 'MobilePay',
        'code' => 'mobilepay',
        'provider' => 'other',
        'enabled' => true,
        'pos_suitable' => true,
        'sort_order' => 1,
        'minimum_amount_kroner' => null,
        'saf_t_payment_code' => '12000',
        'saf_t_event_code' => '13018',
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
        'sku' => 'INV-'.uniqid(),
        'inventory_quantity' => 5,
        'inventory_policy' => 'deny',
        'price_amount' => 10000,
        'currency' => 'nok',
        'active' => true,
    ]);

    return compact('user', 'store', 'session', 'product', 'variant');
}

test('product inventory API returns 403 when inventory add-on is disabled', function () {
    extract(inventoryTestContext());
    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/products/{$product->id}/inventory");

    $response->assertForbidden()
        ->assertJsonFragment(['error' => 'Inventory add-on is not enabled for this store.']);
});

test('product inventory API returns 200 when inventory add-on is enabled', function () {
    extract(inventoryTestContext());
    Addon::factory()->inventory()->create(['store_id' => $store->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson("/api/products/{$product->id}/inventory");

    $response->assertOk()
        ->assertJsonPath('product.id', $product->id);
});

test('cash purchase decrements stock when inventory add-on and product tracking are enabled', function () {
    extract(inventoryTestContext());
    Addon::factory()->inventory()->create(['store_id' => $store->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
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

    $response->assertCreated();

    $variant->refresh();
    expect($variant->inventory_quantity)->toBe(3);

    expect(
        InventoryStockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('reason', InventoryStockMovement::REASON_SALE)
            ->exists()
    )->toBeTrue();
});

test('purchase returns 422 insufficient stock when deny policy and not enough quantity', function () {
    extract(inventoryTestContext());
    Addon::factory()->inventory()->create(['store_id' => $store->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
        'cart' => [
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 10,
                    'unit_price' => 10000,
                ],
            ],
            'total' => 100000,
            'currency' => 'nok',
        ],
        'metadata' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'insufficient_stock')
        ->assertJsonPath('lines.0.variant_id', $variant->id);

    $variant->refresh();
    expect($variant->inventory_quantity)->toBe(5);
});

test('split payment purchase applies inventory deduction once', function () {
    extract(inventoryTestContext());
    Addon::factory()->inventory()->create(['store_id' => $store->id]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payments' => [
            [
                'payment_method_code' => 'cash',
                'amount' => 10000,
                'metadata' => [],
            ],
            [
                'payment_method_code' => 'mobilepay',
                'amount' => 10000,
                'metadata' => [],
            ],
        ],
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

    $response->assertCreated();

    $variant->refresh();
    expect($variant->inventory_quantity)->toBe(3);

    expect(
        InventoryStockMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('reason', InventoryStockMovement::REASON_SALE)
            ->count()
    )->toBe(1);
});

test('full refund restores stock for tracked variant', function () {
    extract(inventoryTestContext());
    Addon::factory()->inventory()->create(['store_id' => $store->id]);

    Sanctum::actingAs($user, ['*']);

    $purchaseResponse = $this->postJson('/api/purchases', [
        'pos_session_id' => $session->id,
        'payment_method_code' => 'cash',
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

    $purchaseResponse->assertCreated();
    $chargeId = $purchaseResponse->json('data.charge.id');

    $variant->refresh();
    expect($variant->inventory_quantity)->toBe(3);

    $refundResponse = $this->postJson("/api/purchases/{$chargeId}/refund", []);

    $refundResponse->assertOk();

    $variant->refresh();
    expect($variant->inventory_quantity)->toBe(5);
});
