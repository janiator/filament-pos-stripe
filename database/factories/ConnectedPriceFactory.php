<?php

namespace Database\Factories;

use App\Models\ConnectedPrice;
use App\Models\Store;
use App\Models\ConnectedProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConnectedPrice>
 */
class ConnectedPriceFactory extends Factory
{
    protected $model = ConnectedPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory()->create();
        $product = ConnectedProduct::factory()->create([
            'stripe_account_id' => $store->stripe_account_id,
        ]);

        return [
            'stripe_price_id' => 'price_' . fake()->unique()->bothify('????????????????????'),
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_product_id' => $product->stripe_product_id,
            'unit_amount' => fake()->numberBetween(1000, 100000), // 10.00 to 1000.00 NOK
            'currency' => 'nok',
            'type' => 'one_time',
            'recurring_interval' => null,
            'recurring_interval_count' => null,
            'recurring_usage_type' => null,
            'recurring_aggregate_usage' => null,
            'active' => true,
            'metadata' => [],
            'nickname' => fake()->optional()->words(2, true),
            'billing_scheme' => null,
            'tiers_mode' => null,
        ];
    }
}

