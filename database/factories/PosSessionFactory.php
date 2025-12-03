<?php

namespace Database\Factories;

use App\Models\PosSession;
use App\Models\Store;
use App\Models\PosDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosSession>
 */
class PosSessionFactory extends Factory
{
    protected $model = PosSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['open', 'closed']);
        $openedAt = fake()->dateTimeBetween('-1 week', 'now');
        
        return [
            'store_id' => Store::factory(),
            'pos_device_id' => PosDevice::factory(),
            'user_id' => User::factory(),
            'session_number' => fake()->unique()->numerify('######'),
            'status' => $status,
            'opened_at' => $openedAt,
            'closed_at' => $status === 'closed' ? fake()->dateTimeBetween($openedAt, 'now') : null,
            'opening_balance' => fake()->numberBetween(0, 100000),
            'expected_cash' => 0, // Will be calculated from charges
            'actual_cash' => $status === 'closed' ? fake()->numberBetween(0, 200000) : null,
            'cash_difference' => $status === 'closed' ? fake()->numberBetween(-5000, 5000) : null,
            'opening_notes' => fake()->optional()->sentence(),
            'closing_notes' => $status === 'closed' ? fake()->optional()->sentence() : null,
            'opening_data' => [],
            'closing_data' => [],
        ];
    }
}
