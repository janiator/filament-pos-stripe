<?php

namespace Database\Factories;

use App\Models\PosDevice;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosDevice>
 */
class PosDeviceFactory extends Factory
{
    protected $model = PosDevice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'device_name' => fake()->word() . ' POS Device',
            'device_identifier' => fake()->uuid(),
            'platform' => fake()->randomElement(['ios', 'android', 'web', 'desktop']),
            'device_model' => fake()->word(),
            'device_status' => fake()->randomElement(['active', 'inactive']),
            'device_metadata' => [],
            'last_seen_at' => now(),
        ];
    }
}
