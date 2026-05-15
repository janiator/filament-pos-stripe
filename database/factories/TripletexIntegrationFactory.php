<?php

namespace Database\Factories;

use App\Enums\PowerOfficeMappingBasis;
use App\Enums\TripletexEnvironment;
use App\Enums\TripletexIntegrationStatus;
use App\Models\Store;
use App\Models\TripletexIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripletexIntegration>
 */
class TripletexIntegrationFactory extends Factory
{
    protected $model = TripletexIntegration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'status' => TripletexIntegrationStatus::NotConnected,
            'environment' => TripletexEnvironment::Test,
            'consumer_token' => null,
            'employee_token' => null,
            'mapping_basis' => PowerOfficeMappingBasis::Vat,
            'sync_enabled' => true,
            'auto_sync_on_z_report' => true,
            'auto_sync_payouts' => false,
            'z_report_include_settlement' => false,
            'skip_payout_bank_transfer' => false,
            'last_synced_at' => null,
            'last_error' => null,
            'settings' => null,
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TripletexIntegrationStatus::Connected,
            'consumer_token' => 'test-consumer-token-'.fake()->uuid(),
            'employee_token' => 'test-employee-token-'.fake()->uuid(),
        ]);
    }
}
