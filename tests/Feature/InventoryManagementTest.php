<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected ConnectedProduct $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create([
            'stripe_account_id' => 'acct_test_' . fake()->uuid(),
        ]);
        $this->user->stores()->attach($this->store);
        $this->user->setCurrentStore($this->store);

        $this->product = ConnectedProduct::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);

        $this->variant = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'sku' => 'TEST-SKU-001',
            'inventory_quantity' => 10,
            'inventory_policy' => 'deny',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Test updating variant inventory
     */
    public function test_can_update_variant_inventory(): void
    {
        $response = $this->putJson("/api/variants/{$this->variant->id}/inventory", [
            'inventory_quantity' => 20,
            'inventory_policy' => 'continue',
            'inventory_management' => 'manual',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'variant' => [
                'id',
                'sku',
                'inventory' => [
                    'quantity',
                    'in_stock',
                    'policy',
                    'management',
                    'tracked',
                ],
            ],
        ]);

        $this->variant->refresh();
        $this->assertEquals(20, $this->variant->inventory_quantity);
        $this->assertEquals('continue', $this->variant->inventory_policy);
        $this->assertEquals('manual', $this->variant->inventory_management);
        $this->assertTrue($this->variant->in_stock);
    }

    /**
     * Test adjusting inventory (adding)
     */
    public function test_can_adjust_inventory_add(): void
    {
        $initialQuantity = $this->variant->inventory_quantity;

        $response = $this->postJson("/api/variants/{$this->variant->id}/inventory/adjust", [
            'quantity' => 5,
            'reason' => 'Restock',
            'note' => 'Received new shipment',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'variant' => [
                'inventory' => [
                    'quantity' => $initialQuantity + 5,
                    'adjustment' => 5,
                    'previous_quantity' => $initialQuantity,
                ],
            ],
        ]);

        $this->variant->refresh();
        $this->assertEquals($initialQuantity + 5, $this->variant->inventory_quantity);
    }

    /**
     * Test adjusting inventory (subtracting)
     */
    public function test_can_adjust_inventory_subtract(): void
    {
        $initialQuantity = $this->variant->inventory_quantity;

        $response = $this->postJson("/api/variants/{$this->variant->id}/inventory/adjust", [
            'quantity' => -3,
            'reason' => 'Sale',
            'note' => 'Sold 3 units',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'variant' => [
                'inventory' => [
                    'quantity' => $initialQuantity - 3,
                    'adjustment' => -3,
                    'previous_quantity' => $initialQuantity,
                ],
            ],
        ]);

        $this->variant->refresh();
        $this->assertEquals($initialQuantity - 3, $this->variant->inventory_quantity);
    }

    /**
     * Test adjusting inventory prevents negative
     */
    public function test_adjust_inventory_prevents_negative(): void
    {
        $this->variant->update(['inventory_quantity' => 5]);

        $response = $this->postJson("/api/variants/{$this->variant->id}/inventory/adjust", [
            'quantity' => -10, // Try to subtract more than available
            'reason' => 'Sale',
        ]);

        $response->assertStatus(200);
        
        $this->variant->refresh();
        $this->assertEquals(0, $this->variant->inventory_quantity); // Should be 0, not -5
    }

    /**
     * Test setting inventory quantity directly
     */
    public function test_can_set_inventory_quantity(): void
    {
        $response = $this->postJson("/api/variants/{$this->variant->id}/inventory/set", [
            'quantity' => 25,
            'reason' => 'Stock count',
            'note' => 'Physical inventory count',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'variant' => [
                'inventory' => [
                    'quantity' => 25,
                    'previous_quantity' => 10,
                ],
            ],
        ]);

        $this->variant->refresh();
        $this->assertEquals(25, $this->variant->inventory_quantity);
    }

    /**
     * Test getting product inventory
     */
    public function test_can_get_product_inventory(): void
    {
        // Create additional variants
        $variant2 = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'inventory_quantity' => 15,
        ]);

        $variant3 = ProductVariant::factory()->noInventoryTracking()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);

        $response = $this->getJson("/api/products/{$this->product->id}/inventory");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'product' => ['id', 'name'],
            'variants' => [
                '*' => [
                    'id',
                    'sku',
                    'variant_name',
                    'inventory' => [
                        'quantity',
                        'in_stock',
                        'policy',
                        'tracked',
                    ],
                ],
            ],
            'summary' => [
                'total_quantity',
                'tracking_inventory',
                'variants_count',
                'in_stock_count',
                'out_of_stock_count',
            ],
        ]);

        $data = $response->json();
        $this->assertCount(3, $data['variants']);
        $this->assertEquals(25, $data['summary']['total_quantity']); // 10 + 15
        $this->assertTrue($data['summary']['tracking_inventory']);
        // Count variants that are actually in stock (quantity > 0 or policy = continue)
        $inStockCount = collect($data['variants'])->filter(function ($v) {
            return $v['inventory']['in_stock'] === true;
        })->count();
        $this->assertGreaterThanOrEqual(2, $inStockCount);
    }

    /**
     * Test bulk update inventory
     */
    public function test_can_bulk_update_inventory(): void
    {
        $variant2 = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'inventory_quantity' => 5,
        ]);

        $response = $this->postJson('/api/inventory/bulk-update', [
            'variants' => [
                ['variant_id' => $this->variant->id, 'quantity' => 30],
                ['variant_id' => $variant2->id, 'quantity' => 20],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'updated' => [
                '*' => ['id', 'sku', 'inventory_quantity', 'in_stock'],
            ],
            'errors',
            'summary' => ['updated_count', 'error_count'],
        ]);

        $data = $response->json();
        $this->assertEquals(2, $data['summary']['updated_count']);
        $this->assertEquals(0, $data['summary']['error_count']);

        $this->variant->refresh();
        $variant2->refresh();
        $this->assertEquals(30, $this->variant->inventory_quantity);
        $this->assertEquals(20, $variant2->inventory_quantity);
    }

    /**
     * Test validation errors
     */
    public function test_inventory_update_validation(): void
    {
        // Test negative quantity
        $response = $this->putJson("/api/variants/{$this->variant->id}/inventory", [
            'inventory_quantity' => -5,
        ]);

        $response->assertStatus(422);

        // Test invalid policy
        $response = $this->putJson("/api/variants/{$this->variant->id}/inventory", [
            'inventory_policy' => 'invalid',
        ]);

        $response->assertStatus(422);

        // Test missing quantity in adjust
        $response = $this->postJson("/api/variants/{$this->variant->id}/inventory/adjust", [
            'reason' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_inventory_access(): void
    {
        $otherStore = Store::factory()->create();
        $otherProduct = ConnectedProduct::factory()->create([
            'stripe_account_id' => $otherStore->stripe_account_id,
        ]);
        $otherVariant = ProductVariant::factory()->create([
            'connected_product_id' => $otherProduct->id,
            'stripe_account_id' => $otherStore->stripe_account_id,
        ]);

        // Try to access variant from different store
        $response = $this->putJson("/api/variants/{$otherVariant->id}/inventory", [
            'inventory_quantity' => 20,
        ]);

        $response->assertStatus(404); // Should not find variant for current store
    }
}

