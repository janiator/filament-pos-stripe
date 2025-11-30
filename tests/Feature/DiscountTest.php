<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\Discount;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class DiscountTest extends TestCase
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
            'price_amount' => 10000, // 100.00 NOK
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Test creating a discount
     */
    public function test_can_create_discount(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => 'Summer Sale',
            'description' => '20% off all products',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'active' => true,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'title' => 'Summer Sale',
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);
    }

    /**
     * Test discount validation - active and within date range
     */
    public function test_discount_is_valid_when_active_and_in_date_range(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => 'Valid Discount',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'active' => true,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDay(),
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $this->assertTrue($discount->isValid());
    }

    /**
     * Test discount validation - inactive discount
     */
    public function test_discount_is_invalid_when_inactive(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => 'Inactive Discount',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'active' => false,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $this->assertFalse($discount->isValid());
    }

    /**
     * Test discount validation - expired discount
     */
    public function test_discount_is_invalid_when_expired(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => 'Expired Discount',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'active' => true,
            'ends_at' => Carbon::now()->subDay(),
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $this->assertFalse($discount->isValid());
    }

    /**
     * Test discount calculation - percentage
     */
    public function test_calculates_percentage_discount_correctly(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => '20% Off',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'active' => true,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $discountAmount = $discount->calculateDiscount(10000); // 100.00 NOK
        $this->assertEquals(2000, $discountAmount); // 20.00 NOK
    }

    /**
     * Test discount calculation - fixed amount
     */
    public function test_calculates_fixed_amount_discount_correctly(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => '10 NOK Off',
            'discount_type' => 'fixed_amount',
            'discount_value' => 1000, // 10.00 NOK in cents
            'currency' => 'nok',
            'active' => true,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $discountAmount = $discount->calculateDiscount(10000); // 100.00 NOK
        $this->assertEquals(1000, $discountAmount); // 10.00 NOK
    }

    /**
     * Test discount service - get applicable discounts
     */
    public function test_discount_service_returns_applicable_discounts(): void
    {
        Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => 'Applicable Discount',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'currency' => 'nok',
            'active' => true,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $service = new DiscountService();
        $discounts = $service->getApplicableDiscounts($this->variant);

        $this->assertCount(1, $discounts);
        $this->assertEquals('Applicable Discount', $discounts->first()->title);
    }

    /**
     * Test discount service - calculates best discount
     */
    public function test_discount_service_returns_best_discount(): void
    {
        Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => '10% Off',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'active' => true,
            'priority' => 1,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => '20% Off',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'active' => true,
            'priority' => 2,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $service = new DiscountService();
        $bestDiscount = $service->getBestDiscount($this->variant);

        $this->assertNotNull($bestDiscount);
        $this->assertEquals('20% Off', $bestDiscount->title);
    }

    /**
     * Test discount service - calculates discounted price
     */
    public function test_discount_service_calculates_discounted_price(): void
    {
        Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => '20% Off',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'active' => true,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'all_products',
        ]);

        $service = new DiscountService();
        $result = $service->calculateDiscountedPrice($this->variant);

        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(8000, $result['discounted_price']); // 100 - 20%
        $this->assertEquals(2000, $result['discount_amount']);
        $this->assertNotNull($result['discount']);
    }

    /**
     * Test discount minimum requirement - purchase amount
     */
    public function test_discount_respects_minimum_purchase_amount(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => 'Min Purchase Discount',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'active' => true,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'minimum_purchase_amount',
            'minimum_requirement_value' => 5000, // 50.00 NOK
            'applicable_to' => 'all_products',
        ]);

        $this->assertTrue($discount->meetsMinimumRequirement(6000)); // 60.00 NOK
        $this->assertFalse($discount->meetsMinimumRequirement(4000)); // 40.00 NOK
    }

    /**
     * Test discount applies to specific products
     */
    public function test_discount_applies_to_specific_products(): void
    {
        $discount = Discount::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'title' => 'Product Specific Discount',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'currency' => 'nok',
            'active' => true,
            'customer_selection' => 'all',
            'minimum_requirement_type' => 'none',
            'applicable_to' => 'specific_products',
            'product_ids' => [$this->product->id],
        ]);

        $this->assertTrue($discount->appliesToProduct($this->product->id));
        
        $otherProduct = ConnectedProduct::factory()->create([
            'stripe_account_id' => $this->store->stripe_account_id,
        ]);
        $this->assertFalse($discount->appliesToProduct($otherProduct->id));
    }
}
