<?php

namespace Database\Factories;

use App\Models\PosEvent;
use App\Models\Store;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use App\Models\ConnectedCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PosEvent>
 */
class PosEventFactory extends Factory
{
    protected $model = PosEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'pos_device_id' => PosDevice::factory(),
            'pos_session_id' => PosSession::factory(),
            'user_id' => User::factory(),
            'event_code' => fake()->randomElement([
                PosEvent::EVENT_SESSION_OPENED,
                PosEvent::EVENT_SESSION_CLOSED,
                PosEvent::EVENT_SALES_RECEIPT,
                PosEvent::EVENT_CASH_PAYMENT,
                PosEvent::EVENT_CARD_PAYMENT,
            ]),
            'event_type' => fake()->randomElement(['session', 'transaction', 'payment', 'report']),
            'description' => fake()->sentence(),
            'related_charge_id' => null,
            'event_data' => [],
            'occurred_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
