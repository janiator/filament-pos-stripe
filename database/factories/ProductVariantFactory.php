<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\ConnectedProduct;
use App\Models\ConnectedPrice;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

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
        
        $price = ConnectedPrice::factory()->create([
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_product_id' => $product->stripe_product_id,
        ]);

        return [
            'connected_product_id' => $product->id,
            'stripe_account_id' => $store->stripe_account_id,
            'stripe_price_id' => $price->stripe_price_id,
            'sku' => fake()->unique()->bothify('SKU-#######'),
            'barcode' => fake()->optional()->ean13(),
            'option1_name' => fake()->optional(0.8)->randomElement(['Size', 'Color', 'Material']),
            'option1_value' => function (array $attributes) {
                if (!$attributes['option1_name']) {
                    return null;
                }
                return match($attributes['option1_name']) {
                    'Size' => fake()->randomElement(['Small', 'Medium', 'Large', 'XL']),
                    'Color' => fake()->colorName(),
                    'Material' => fake()->randomElement(['Cotton', 'Polyester', 'Wool']),
                    default => fake()->word(),
                };
            },
            'option2_name' => fake()->optional(0.5)->randomElement(['Color', 'Style', 'Pattern']),
            'option2_value' => function (array $attributes) {
                if (!$attributes['option2_name']) {
                    return null;
                }
                return fake()->word();
            },
            'option3_name' => fake()->optional(0.3)->word(),
            'option3_value' => fake()->optional(0.3)->word(),
            'price_amount' => fake()->numberBetween(1000, 100000), // 10.00 to 1000.00 NOK
            'currency' => 'nok',
            'compare_at_price_amount' => fake()->optional(0.3)->numberBetween(1500, 150000),
            'weight_grams' => fake()->optional()->numberBetween(100, 5000),
            'requires_shipping' => true,
            'taxable' => true,
            'inventory_quantity' => fake()->optional(0.7)->numberBetween(0, 100),
            'inventory_policy' => fake()->randomElement(['deny', 'continue']),
            'inventory_management' => fake()->optional(0.5)->randomElement(['shopify', 'manual', null]),
            'image_url' => fake()->optional(0.3)->imageUrl(),
            'metadata' => [],
            'active' => true,
        ];
    }

    /**
     * Indicate that the variant is out of stock
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'inventory_quantity' => 0,
            'inventory_policy' => 'deny',
        ]);
    }

    /**
     * Indicate that the variant has no inventory tracking
     */
    public function noInventoryTracking(): static
    {
        return $this->state(fn (array $attributes) => [
            'inventory_quantity' => null,
            'inventory_management' => null,
        ]);
    }

    /**
     * Indicate that the variant allows backorders
     */
    public function allowsBackorders(): static
    {
        return $this->state(fn (array $attributes) => [
            'inventory_policy' => 'continue',
        ]);
    }
}

