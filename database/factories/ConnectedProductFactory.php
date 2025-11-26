<?php

namespace Database\Factories;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConnectedProduct>
 */
class ConnectedProductFactory extends Factory
{
    protected $model = ConnectedProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stripe_product_id' => 'prod_' . fake()->unique()->bothify('????????????????????'),
            'stripe_account_id' => Store::factory()->create()->stripe_account_id,
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'active' => true,
            'images' => [],
            'product_meta' => [],
            'type' => fake()->randomElement(['service', 'good']),
            'url' => fake()->optional()->url(),
            'package_dimensions' => null,
            'shippable' => false,
            'statement_descriptor' => null,
            'tax_code' => null,
            'unit_label' => null,
            'default_price' => null,
            'price' => fake()->numberBetween(1000, 100000) / 100,
            'currency' => 'nok',
            'article_group_code' => fake()->randomElement(['04003', '04006', '04014', '04004']),
            'product_code' => fake()->unique()->numerify('PLU#####'),
        ];
    }
}
