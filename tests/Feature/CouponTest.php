<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\Coupon;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create([
            'stripe_account_id' => 'acct_test_' . fake()->uuid(),
        ]);
        $this->user->stores()->attach($this->store);
        $this->user->setCurrentStore($this->store);

        Sanctum::actingAs($this->user, ['*']);
    }

    /**
     * Test creating a coupon
     */
    public function test_can_create_coupon(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'SUMMER2024',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
        ]);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => 'SUMMER2024',
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);
    }

    /**
     * Test coupon validation - active and not expired
     */
    public function test_coupon_is_valid_when_active_and_not_expired(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'VALID2024',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
            'redeem_by' => Carbon::now()->addDay(),
        ]);

        $this->assertTrue($coupon->isValid());
    }

    /**
     * Test coupon validation - inactive coupon
     */
    public function test_coupon_is_invalid_when_inactive(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'INACTIVE2024',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => false,
        ]);

        $this->assertFalse($coupon->isValid());
    }

    /**
     * Test coupon validation - expired coupon
     */
    public function test_coupon_is_invalid_when_expired(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'EXPIRED2024',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
            'redeem_by' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($coupon->isValid());
    }

    /**
     * Test coupon validation - max redemptions reached
     */
    public function test_coupon_is_invalid_when_max_redemptions_reached(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'MAXED2024',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
            'max_redemptions' => 5,
            'times_redeemed' => 5,
        ]);

        $this->assertFalse($coupon->isValid());
    }

    /**
     * Test coupon calculation - percentage
     */
    public function test_calculates_percentage_coupon_correctly(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'PERCENT20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
        ]);

        $discountAmount = $coupon->calculateDiscount(10000); // 100.00 NOK
        $this->assertEquals(2000, $discountAmount); // 20.00 NOK
    }

    /**
     * Test coupon calculation - fixed amount
     */
    public function test_calculates_fixed_amount_coupon_correctly(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'FIXED10',
            'discount_type' => 'fixed_amount',
            'discount_value' => 1000, // 10.00 NOK in cents
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
        ]);

        $discountAmount = $coupon->calculateDiscount(10000); // 100.00 NOK
        $this->assertEquals(1000, $discountAmount); // 10.00 NOK
    }

    /**
     * Test coupon minimum amount requirement
     */
    public function test_coupon_respects_minimum_amount(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'MIN50',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
            'minimum_amount' => 5000, // 50.00 NOK
            'minimum_amount_currency' => 'nok',
        ]);

        $this->assertTrue($coupon->meetsMinimumAmount(6000, 'nok')); // 60.00 NOK
        $this->assertFalse($coupon->meetsMinimumAmount(4000, 'nok')); // 40.00 NOK
    }

    /**
     * Test finding coupon by code (case-insensitive)
     */
    public function test_finds_coupon_by_code_case_insensitive(): void
    {
        Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'SUMMER2024',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
        ]);

        $found = Coupon::findByCode('summer2024', $this->store->stripe_account_id);
        $this->assertNotNull($found);
        $this->assertEquals('SUMMER2024', $found->code);

        $found2 = Coupon::findByCode('SUMMER2024', $this->store->stripe_account_id);
        $this->assertNotNull($found2);
        $this->assertEquals('SUMMER2024', $found2->code);
    }

    /**
     * Test discount service - validates coupon
     */
    public function test_discount_service_validates_coupon(): void
    {
        Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'VALID2024',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
            'minimum_amount' => 1000, // 10.00 NOK
            'minimum_amount_currency' => 'nok',
        ]);

        $service = new DiscountService();
        
        // Valid coupon with sufficient amount
        $coupon = $service->validateCoupon('VALID2024', $this->store->stripe_account_id, 5000, 'nok');
        $this->assertNotNull($coupon);
        $this->assertEquals('VALID2024', $coupon->code);

        // Invalid coupon - insufficient amount
        $coupon2 = $service->validateCoupon('VALID2024', $this->store->stripe_account_id, 500, 'nok');
        $this->assertNull($coupon2);

        // Invalid coupon - wrong code
        $coupon3 = $service->validateCoupon('INVALID', $this->store->stripe_account_id, 5000, 'nok');
        $this->assertNull($coupon3);
    }

    /**
     * Test discount service - calculates discount with coupon
     */
    public function test_discount_service_calculates_with_coupon(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'COUPON20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
        ]);

        $service = new DiscountService();
        $result = $service->calculateWithCoupon(10000, $coupon); // 100.00 NOK

        $this->assertEquals(10000, $result['original_price']);
        $this->assertEquals(8000, $result['discounted_price']); // 100 - 20%
        $this->assertEquals(2000, $result['discount_amount']);
        $this->assertEquals($coupon->id, $result['coupon']->id);
    }

    /**
     * Test coupon increment redemptions
     */
    public function test_coupon_increments_redemptions(): void
    {
        $coupon = Coupon::create([
            'store_id' => $this->store->id,
            'stripe_account_id' => $this->store->stripe_account_id,
            'code' => 'REDEEM2024',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'currency' => 'nok',
            'duration' => 'once',
            'active' => true,
            'times_redeemed' => 0,
        ]);

        $this->assertEquals(0, $coupon->times_redeemed);
        
        $coupon->incrementRedemptions();
        $coupon->refresh();
        
        $this->assertEquals(1, $coupon->times_redeemed);
    }
}
