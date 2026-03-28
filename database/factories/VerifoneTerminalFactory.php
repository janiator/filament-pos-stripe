<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VerifoneTerminal>
 */
class VerifoneTerminalFactory extends Factory
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
            'pos_device_id' => null,
            'terminal_identifier' => 'vm'.fake()->unique()->numerify('#####'),
            'display_name' => 'Verifone '.fake()->numerify('###'),
            'sale_id' => 'POS'.fake()->numerify('###'),
            'operator_id' => fake()->numerify('########'),
            'site_entity_id' => fake()->uuid(),
            'is_active' => true,
            'terminal_metadata' => [],
        ];
    }
}
