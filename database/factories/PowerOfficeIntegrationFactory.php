<?php

namespace Database\Factories;

use App\Enums\PowerOfficeEnvironment;
use App\Enums\PowerOfficeIntegrationStatus;
use App\Enums\PowerOfficeMappingBasis;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerOfficeIntegration>
 */
class PowerOfficeIntegrationFactory extends Factory
{
    protected $model = PowerOfficeIntegration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'status' => PowerOfficeIntegrationStatus::NotConnected,
            'environment' => PowerOfficeEnvironment::Dev,
            'client_key' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'mapping_basis' => PowerOfficeMappingBasis::Vat,
            'auto_sync_on_z_report' => true,
            'sync_enabled' => true,
            'last_onboarded_at' => null,
            'onboarding_completed_at' => now(),
            'last_synced_at' => null,
            'last_error' => null,
            'settings' => null,
            'onboarding_state_token' => null,
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PowerOfficeIntegrationStatus::Connected,
            'client_key' => 'test-client-key-'.fake()->uuid(),
            'last_onboarded_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
    }

    public function onboardingWizard(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_completed_at' => null,
        ]);
    }
}
