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

class ProductVariantsApiTest extends TestCase
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
     * Test products API includes variants
     */
    public function test_products_api_includes_variants(): void
    {
        $price = ConnectedPrice::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'stripe_product_id' => $this->product->stripe_product_id,
        ]);

        $variant1 = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'stripe_price_id' => $price->stripe_price_id,
            'option1_name' => 'Size',
            'option1_value' => 'Large',
            'option2_name' => 'Color',
            'option2_value' => 'Red',
            'sku' => 'SKU-001',
            'price_amount' => 5999,
            'inventory_quantity' => 10,
        ]);

        $variant2 = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'option1_name' => 'Size',
            'option1_value' => 'Small',
            'sku' => 'SKU-002',
            'price_amount' => 4999,
            'inventory_quantity' => 5,
        ]);

        $response = $this->getJson("/api/products/{$this->product->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'product' => [
                'id',
                'name',
                'variants' => [
                    '*' => [
                        'id',
                        'sku',
                        'variant_name',
                        'options',
                        'price' => [
                            'amount',
                            'amount_formatted',
                            'currency',
                        ],
                        'inventory' => [
                            'quantity',
                            'in_stock',
                            'policy',
                            'tracked',
                        ],
                    ],
                ],
                'variants_count',
                'inventory' => [
                    'tracked',
                    'total_quantity',
                    'in_stock_variants',
                    'out_of_stock_variants',
                    'all_in_stock',
                ],
            ],
        ]);

        $data = $response->json('product');
        $this->assertCount(2, $data['variants']);
        $this->assertEquals(2, $data['variants_count']);
        $this->assertEquals(15, $data['inventory']['total_quantity']); // 10 + 5
        $this->assertEquals(2, $data['inventory']['in_stock_variants']);
        $this->assertTrue($data['inventory']['all_in_stock']);

        // Check variant details
        $variantData = collect($data['variants'])->firstWhere('sku', 'SKU-001');
        $this->assertNotNull($variantData);
        $this->assertStringContainsString('Large', $variantData['variant_name']);
        $this->assertStringContainsString('Red', $variantData['variant_name']);
        $this->assertEquals(5999, $variantData['price']['amount']);
        $this->assertEquals(10, $variantData['inventory']['quantity']);
        $this->assertTrue($variantData['inventory']['in_stock']);
    }

    /**
     * Test products list API includes variants summary
     */
    public function test_products_list_includes_variants_summary(): void
    {
        ProductVariant::factory()->count(3)->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'inventory_quantity' => 10,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'product' => [
                '*' => [
                    'id',
                    'name',
                    'variants',
                    'variants_count',
                    'inventory',
                ],
            ],
        ]);

        $products = $response->json('product');
        $product = collect($products)->firstWhere('id', $this->product->id);
        
        $this->assertNotNull($product);
        $this->assertCount(3, $product['variants']);
        $this->assertEquals(3, $product['variants_count']);
        $this->assertEquals(30, $product['inventory']['total_quantity']);
    }

    /**
     * Test product with no variants
     */
    public function test_product_without_variants(): void
    {
        $response = $this->getJson("/api/products/{$this->product->id}");

        $response->assertStatus(200);
        $data = $response->json('product');
        
        $this->assertIsArray($data['variants']);
        $this->assertCount(0, $data['variants']);
        $this->assertEquals(0, $data['variants_count']);
        $this->assertFalse($data['inventory']['tracked']);
        $this->assertNull($data['inventory']['total_quantity']);
    }

    /**
     * Test product with out of stock variants
     */
    public function test_product_with_out_of_stock_variants(): void
    {
        $inStock = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'inventory_quantity' => 10,
            'inventory_policy' => 'deny',
        ]);

        $outOfStock = ProductVariant::factory()->outOfStock()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);

        $response = $this->getJson("/api/products/{$this->product->id}");

        $response->assertStatus(200);
        $data = $response->json('product');
        
        $this->assertEquals(1, $data['inventory']['in_stock_variants']);
        $this->assertEquals(1, $data['inventory']['out_of_stock_variants']);
        $this->assertFalse($data['inventory']['all_in_stock']);
    }

    /**
     * Test variant options structure
     */
    public function test_variant_options_structure(): void
    {
        $variant = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'option1_name' => 'Size',
            'option1_value' => 'Large',
            'option2_name' => 'Color',
            'option2_value' => 'Red',
            'option3_name' => 'Material',
            'option3_value' => 'Cotton',
        ]);

        $response = $this->getJson("/api/products/{$this->product->id}");

        $response->assertStatus(200);
        $variantData = collect($response->json('product.variants'))->first();
        
        $this->assertNotNull($variantData['options']['option1']);
        $this->assertEquals('Size', $variantData['options']['option1']['name']);
        $this->assertEquals('Large', $variantData['options']['option1']['value']);
        
        $this->assertNotNull($variantData['options']['option2']);
        $this->assertEquals('Color', $variantData['options']['option2']['name']);
        $this->assertEquals('Red', $variantData['options']['option2']['value']);
        
        $this->assertNotNull($variantData['options']['option3']);
        $this->assertEquals('Material', $variantData['options']['option3']['name']);
        $this->assertEquals('Cotton', $variantData['options']['option3']['value']);
    }
}

