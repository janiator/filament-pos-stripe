<?php

namespace Database\Factories;

use App\Enums\AddonType;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Addon>
 */
class AddonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => AddonType::WebflowCms,
            'is_active' => true,
        ];
    }

    public function eventTickets(): static
    {
        return $this->state(fn (array $attributes) => ['type' => AddonType::EventTickets]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
