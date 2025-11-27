<?php

namespace Tests\Feature;

use App\Actions\ConnectedProducts\CreateVariantProductInStripe;
use App\Actions\ConnectedProducts\UpdateVariantProductToStripe;
use App\Jobs\SyncVariantProductToStripeJob;
use App\Models\ConnectedProduct;
use App\Models\ConnectedPrice;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class VariantStripeProductTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Store $store;
    protected ConnectedProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create(['stripe_account_id' => 'acct_test_' . $this->faker->uuid]);
        $this->product = ConnectedProduct::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
            'stripe_product_id' => 'prod_test_parent',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that creating a variant creates a Stripe Product.
     */
    public function test_creating_variant_creates_stripe_product(): void
    {
        // Mock the CreateVariantProductInStripe action
        $mockAction = $this->mock(CreateVariantProductInStripe::class);
        $mockAction->shouldReceive('__invoke')
            ->once()
            ->andReturn('prod_test_variant_123');

        // Mock CreateConnectedPriceInStripe
        $mockPriceAction = $this->mock(\App\Actions\ConnectedPrices\CreateConnectedPriceInStripe::class);
        $mockPriceAction->shouldReceive('__invoke')
            ->once()
            ->andReturn('price_test_variant_123');

        // Create variant
        $variant = ProductVariant::factory()->create([
            'connected_product_id' => $this->product->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'option1_name' => 'Size',
            'option1_value' => 'Large',
            'option2_name' => 'Color',
            'option2_value' => 'Red',
            'price_amount' => 2999,
            'currency' => 'nok',
        ]);

        // Refresh to get updated values
        $variant->refresh();

        // Verify Stripe IDs were set
        $this->assertEquals('prod_test_variant_123', $variant->stripe_product_id);
        $this->assertEquals('price_test_variant_123', $variant->stripe_price_id);
    }

    /**
     * Test that variant product name includes variant options.
     */
    public function test_variant_product_name_includes_options(): void
    {
        // Disable event listeners to avoid Stripe calls
        ProductVariant::withoutEvents(function () {
            $variant = ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'option1_name' => 'Size',
                'option1_value' => 'Large',
                'option2_name' => 'Color',
                'option2_value' => 'Red',
            ]);

            $fullTitle = $variant->full_title;
            $this->assertStringContainsString($this->product->name, $fullTitle);
            $this->assertStringContainsString('Large', $fullTitle);
            $this->assertStringContainsString('Red', $fullTitle);
        });
    }

    /**
     * Test that updating a variant dispatches sync job.
     */
    public function test_updating_variant_dispatches_sync_job(): void
    {
        Queue::fake();

        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'stripe_product_id' => 'prod_test_variant_123',
                'option1_value' => 'Large',
                'price_amount' => 2999,
            ]);
        });

        // Update variant
        $variant->update([
            'option1_value' => 'Small',
            'price_amount' => 2499,
        ]);

        // Verify sync job was dispatched
        Queue::assertPushed(SyncVariantProductToStripeJob::class, function ($job) use ($variant) {
            return $job->variant->id === $variant->id;
        });
    }

    /**
     * Test that updating non-syncable fields doesn't dispatch job.
     */
    public function test_updating_non_syncable_fields_doesnt_dispatch_job(): void
    {
        Queue::fake();

        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'stripe_product_id' => 'prod_test_variant_123',
                'inventory_quantity' => 10,
            ]);
        });

        // Update only inventory (not syncable)
        $variant->update([
            'inventory_quantity' => 5,
        ]);

        // Verify sync job was NOT dispatched
        Queue::assertNothingPushed();
    }

    /**
     * Test that deleting a variant archives it in Stripe.
     */
    public function test_deleting_variant_archives_in_stripe(): void
    {
        // Mock Stripe client
        $mockStripe = Mockery::mock('overload:Stripe\StripeClient');
        $mockProducts = Mockery::mock();
        $mockStripe->shouldReceive('__construct')->andReturnSelf();
        $mockStripe->products = $mockProducts;

        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'stripe_product_id' => 'prod_test_variant_123',
            ]);
        });

        // Mock the update call to archive product
        $mockProducts->shouldReceive('update')
            ->once()
            ->with('prod_test_variant_123', ['active' => false], ['stripe_account' => $this->store->stripe_account_id])
            ->andReturn((object)['id' => 'prod_test_variant_123', 'active' => false]);

        // Delete variant
        $variant->delete();

        // If we get here without exception, the mock was called correctly
        $this->assertTrue(true);
    }

    /**
     * Test CreateVariantProductInStripe action.
     */
    public function test_create_variant_product_action(): void
    {
        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'option1_name' => 'Size',
                'option1_value' => 'Large',
                'option2_name' => 'Color',
                'option2_value' => 'Red',
                'sku' => 'TEST-SKU-123',
                'barcode' => '1234567890',
                'price_amount' => 2999,
                'requires_shipping' => true,
            ]);
        });

        // Mock Stripe client
        $mockStripe = Mockery::mock('alias:Stripe\StripeClient');
        $mockProducts = Mockery::mock();
        $mockStripe->shouldReceive('__construct')->andReturnSelf();
        $mockStripe->products = $mockProducts;

        $expectedName = $variant->full_title;
        $mockStripeProduct = (object)[
            'id' => 'prod_test_variant_123',
            'name' => $expectedName,
        ];

        $mockProducts->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($expectedName) {
                return $data['name'] === $expectedName
                    && $data['type'] === 'good'
                    && isset($data['metadata']['variant_id'])
                    && isset($data['metadata']['parent_product_id'])
                    && isset($data['metadata']['option1_name'])
                    && isset($data['metadata']['option1_value'])
                    && isset($data['metadata']['sku']);
            }), ['stripe_account' => $this->store->stripe_account_id])
            ->andReturn($mockStripeProduct);

        $action = new CreateVariantProductInStripe();
        $result = $action($variant);

        $this->assertEquals('prod_test_variant_123', $result);
    }

    /**
     * Test UpdateVariantProductToStripe action.
     */
    public function test_update_variant_product_action(): void
    {
        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'stripe_product_id' => 'prod_test_variant_123',
                'option1_value' => 'Large',
                'price_amount' => 2999,
                'active' => true,
                'requires_shipping' => true,
            ]);
        });

        // Mock Stripe client
        $mockStripe = Mockery::mock('alias:Stripe\StripeClient');
        $mockProducts = Mockery::mock();
        $mockStripe->shouldReceive('__construct')->andReturnSelf();
        $mockStripe->products = $mockProducts;

        $mockProducts->shouldReceive('update')
            ->once()
            ->with(
                'prod_test_variant_123',
                Mockery::on(function ($data) {
                    return $data['name'] === $variant->full_title
                        && $data['active'] === true
                        && isset($data['metadata']['variant_id'])
                        && isset($data['metadata']['parent_product_id']);
                }),
                ['stripe_account' => $this->store->stripe_account_id]
            )
            ->andReturn((object)['id' => 'prod_test_variant_123']);

        $action = new UpdateVariantProductToStripe();
        $action($variant);

        // If we get here without exception, the mock was called correctly
        $this->assertTrue(true);
    }

    /**
     * Test that variant metadata includes parent product info.
     */
    public function test_variant_metadata_includes_parent_info(): void
    {
        $this->product->update([
            'product_meta' => [
                'vendor' => 'Test Vendor',
                'tags' => 'clothing, t-shirt',
            ],
        ]);

        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'option1_value' => 'Large',
            ]);
        });

        // Mock Stripe client
        $mockStripe = Mockery::mock('alias:Stripe\StripeClient');
        $mockProducts = Mockery::mock();
        $mockStripe->shouldReceive('__construct')->andReturnSelf();
        $mockStripe->products = $mockProducts;

        $mockProducts->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return isset($data['metadata']['parent_vendor'])
                    && isset($data['metadata']['parent_tags'])
                    && $data['metadata']['parent_vendor'] === 'Test Vendor';
            }), Mockery::any())
            ->andReturn((object)['id' => 'prod_test']);

        $action = new CreateVariantProductInStripe();
        $action($variant);

        $this->assertTrue(true);
    }

    /**
     * Test that variant with image_url uses it in Stripe.
     */
    public function test_variant_uses_image_url_in_stripe(): void
    {
        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'image_url' => 'https://example.com/variant-image.jpg',
            ]);
        });

        // Mock Stripe client
        $mockStripe = Mockery::mock('alias:Stripe\StripeClient');
        $mockProducts = Mockery::mock();
        $mockStripe->shouldReceive('__construct')->andReturnSelf();
        $mockStripe->products = $mockProducts;

        $mockProducts->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return isset($data['images'])
                    && in_array('https://example.com/variant-image.jpg', $data['images']);
            }), Mockery::any())
            ->andReturn((object)['id' => 'prod_test']);

        $action = new CreateVariantProductInStripe();
        $action($variant);

        $this->assertTrue(true);
    }

    /**
     * Test that API response includes stripe_product_id.
     */
    public function test_api_response_includes_stripe_product_id(): void
    {
        $variant = ProductVariant::withoutEvents(function () {
            return ProductVariant::factory()->create([
                'connected_product_id' => $this->product->id,
                'stripe_account_id' => $this->store->stripe_account_id,
                'stripe_product_id' => 'prod_test_variant_123',
                'stripe_price_id' => 'price_test_variant_123',
                'option1_value' => 'Large',
            ]);
        });

        $user = \App\Models\User::factory()->create();
        $user->stores()->attach($this->store);
        $user->setCurrentStore($this->store);
        \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

        $response = $this->getJson("/api/products/{$this->product->id}");

        $response->assertStatus(200);
        $variantData = collect($response->json('product.variants'))->firstWhere('id', $variant->id);
        
        $this->assertNotNull($variantData);
        $this->assertEquals('prod_test_variant_123', $variantData['stripe_product_id']);
        $this->assertEquals('price_test_variant_123', $variantData['stripe_price_id']);
    }
}

