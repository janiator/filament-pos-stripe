<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\Store;
use App\Models\PosSession;
use App\Models\ConnectedCharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receiptType = fake()->randomElement(['sales', 'return', 'copy', 'steb', 'provisional', 'training', 'delivery']);
        
        return [
            'store_id' => Store::factory(),
            'pos_session_id' => PosSession::factory(),
            'charge_id' => ConnectedCharge::factory(),
            'user_id' => User::factory(),
            'receipt_number' => fake()->unique()->numerify('S-######'),
            'receipt_type' => $receiptType,
            'original_receipt_id' => null,
            'receipt_data' => [
                'store' => [
                    'name' => fake()->company(),
                    'address' => fake()->address(),
                ],
                'date' => now()->format('Y-m-d H:i:s'),
                'amount' => fake()->numberBetween(1000, 100000) / 100,
            ],
            'printed' => fake()->boolean(70),
            'printed_at' => fake()->optional(0.7)->dateTimeBetween('-1 week', 'now'),
            'reprint_count' => fake()->numberBetween(0, 3),
        ];
    }
}
