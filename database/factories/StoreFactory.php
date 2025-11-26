<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->slug(),
            'email' => fake()->safeEmail(),
            'stripe_account_id' => 'acct_test_' . fake()->uuid(),
            'commission_type' => 'percentage',
            'commission_rate' => 10,
        ];
    }
}
