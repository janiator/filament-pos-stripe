<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Models\ConnectedPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected ConnectedProduct $product;

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

        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Test creating a product variant
     */
    public function test_can_create_product_variant(): void
    {
        $price = ConnectedPrice::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'stripe_product_id' => $this->product->stripe_product_id,
        ]);

        $variant = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'stripe_price_id' => $price->stripe_price_id,
            'option1_name' => 'Size',
            'option1_value' => 'Large',
            'option2_name' => 'Color',
            'option2_value' => 'Red',
            'sku' => 'TEST-SKU-001',
            'price_amount' => 5999,
            'inventory_quantity' => 10,
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'connected_product_id' => $this->product->id,
            'sku' => 'TEST-SKU-001',
            'option1_value' => 'Large',
            'option2_value' => 'Red',
            'inventory_quantity' => 10,
        ]);

        $this->assertStringContainsString('Large', $variant->variant_name);
        $this->assertStringContainsString('Red', $variant->variant_name);
        $this->assertTrue($variant->in_stock);
    }

    /**
     * Test variant name generation
     */
    public function test_variant_name_generation(): void
    {
        $variant = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'option1_value' => 'Large',
            'option2_value' => 'Red',
            'option3_value' => 'Cotton',
        ]);

        $this->assertEquals('Large / Red / Cotton', $variant->variant_name);

        // Test with no options
        $variantNoOptions = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'option1_value' => null,
            'option2_value' => null,
            'option3_value' => null,
        ]);

        $this->assertEquals('Default', $variantNoOptions->variant_name);
    }

    /**
     * Test inventory stock status
     */
    public function test_inventory_stock_status(): void
    {
        // In stock
        $inStock = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'inventory_quantity' => 10,
            'inventory_policy' => 'deny',
        ]);

        $this->assertTrue($inStock->in_stock);

        // Out of stock
        $outOfStock = ProductVariant::factory()->outOfStock()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);

        $this->assertFalse($outOfStock->in_stock);

        // Not tracking inventory
        $noTracking = ProductVariant::factory()->noInventoryTracking()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);

        $this->assertTrue($noTracking->in_stock); // Should return true if not tracking

        // Backorders allowed
        $backorders = ProductVariant::factory()->allowsBackorders()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'inventory_quantity' => 0,
        ]);

        $this->assertTrue($backorders->in_stock); // Should return true if backorders allowed
    }

    /**
     * Test discount percentage calculation
     */
    public function test_discount_percentage(): void
    {
        $variant = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'price_amount' => 5000, // 50.00 NOK
            'compare_at_price_amount' => 10000, // 100.00 NOK
        ]);

        $this->assertEquals(50.0, $variant->discount_percentage);

        // No discount
        $noDiscount = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'price_amount' => 5000,
            'compare_at_price_amount' => null,
        ]);

        $this->assertNull($noDiscount->discount_percentage);
    }

    /**
     * Test variant relationships
     */
    public function test_variant_relationships(): void
    {
        $price = ConnectedPrice::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'stripe_product_id' => $this->product->stripe_product_id,
        ]);

        $variant = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'stripe_price_id' => $price->stripe_price_id,
        ]);

        // Test product relationship
        $this->assertEquals($this->product->id, $variant->product->id);

        // Test price relationship
        $this->assertNotNull($variant->price);
        $this->assertEquals($price->stripe_price_id, $variant->price->stripe_price_id);

        // Test store relationship
        $this->assertEquals($this->store->stripe_account_id, $variant->store->stripe_account_id);
    }

    /**
     * Test product has variants relationship
     */
    public function test_product_has_variants(): void
    {
        $variant1 = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);

        $variant2 = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);

        $this->product->refresh();
        $this->assertCount(2, $this->product->variants);
        $this->assertTrue($this->product->variants->contains($variant1));
        $this->assertTrue($this->product->variants->contains($variant2));
    }

    /**
     * Test SKU uniqueness per account
     */
    public function test_sku_uniqueness_per_account(): void
    {
        $variant1 = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'sku' => 'UNIQUE-SKU-001',
        ]);

        // Should allow same SKU for different account
        $otherStore = Store::factory()->create();
        $otherProduct = ConnectedProduct::factory()->create([
            'stripe_account_id' => $otherStore->stripe_account_id,
        ]);

        $variant2 = ProductVariant::factory()->create([
            'connected_product_id' => $otherProduct->id,
            'stripe_account_id' => $otherStore->stripe_account_id,
            'sku' => 'UNIQUE-SKU-001', // Same SKU, different account - should work
        ]);

        $this->assertNotEquals($variant1->id, $variant2->id);

        // Should not allow same SKU for same account
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'sku' => 'UNIQUE-SKU-001', // Same SKU, same account - should fail
        ]);
    }
}

