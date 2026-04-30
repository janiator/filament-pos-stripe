<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VerifoneTerminalPayment>
 */
class VerifoneTerminalPaymentFactory extends Factory
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
            'verifone_terminal_id' => null,
            'pos_session_id' => null,
            'pos_device_id' => null,
            'service_id' => fake()->unique()->numerify('####'),
            'sale_id' => 'POS'.fake()->numerify('###'),
            'poiid' => 'vm'.fake()->numerify('#####'),
            'amount_minor' => fake()->numberBetween(100, 50000),
            'currency' => 'NOK',
            'status' => 'pending',
            'provider_payment_reference' => null,
            'provider_transaction_id' => null,
            'provider_message' => null,
            'request_payload' => [],
            'response_payload' => [],
            'status_payload' => [],
            'completed_at' => null,
            'failed_at' => null,
        ];
    }
}
