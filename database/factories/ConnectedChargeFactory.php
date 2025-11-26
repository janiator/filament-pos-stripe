<?php

namespace Database\Factories;

use App\Models\ConnectedCharge;
use App\Models\Store;
use App\Models\PosSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConnectedCharge>
 */
class ConnectedChargeFactory extends Factory
{
    protected $model = ConnectedCharge::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentMethod = fake()->randomElement(['cash', 'card', 'mobile']);
        $status = fake()->randomElement(['succeeded', 'pending', 'failed']);
        
        return [
            'stripe_charge_id' => 'ch_' . fake()->unique()->bothify('????????????????????'),
            'stripe_account_id' => Store::factory()->create()->stripe_account_id,
            'pos_session_id' => PosSession::factory(),
            'stripe_customer_id' => 'cus_' . fake()->bothify('????????????????????'),
            'stripe_payment_intent_id' => 'pi_' . fake()->bothify('????????????????????'),
            'amount' => fake()->numberBetween(1000, 100000),
            'amount_refunded' => 0,
            'currency' => 'nok',
            'status' => $status,
            'payment_method' => $paymentMethod,
            'description' => fake()->sentence(),
            'failure_code' => $status === 'failed' ? fake()->word() : null,
            'failure_message' => $status === 'failed' ? fake()->sentence() : null,
            'captured' => true,
            'refunded' => false,
            'paid' => $status === 'succeeded',
            'paid_at' => $status === 'succeeded' ? fake()->dateTimeBetween('-1 week', 'now') : null,
            'metadata' => [],
            'outcome' => [],
            'charge_type' => 'payment',
            'application_fee_amount' => 0,
            // Don't set codes in factory - let observer handle it to ensure consistency
            'transaction_code' => null,
            'payment_code' => null,
            'tip_amount' => fake()->optional(0.3)->numberBetween(0, 5000) ?? 0,
            'article_group_code' => fake()->randomElement(['04003', '04006', '04014']),
        ];
    }
}
