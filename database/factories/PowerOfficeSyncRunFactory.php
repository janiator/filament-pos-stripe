<?php

namespace Database\Factories;

use App\Enums\PowerOfficeSyncRunStatus;
use App\Models\PosSession;
use App\Models\PowerOfficeIntegration;
use App\Models\PowerOfficeSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PowerOfficeSyncRun>
 */
class PowerOfficeSyncRunFactory extends Factory
{
    protected $model = PowerOfficeSyncRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'power_office_integration_id' => PowerOfficeIntegration::factory(),
            'store_id' => function (array $attributes): int {
                return PowerOfficeIntegration::query()
                    ->findOrFail($attributes['power_office_integration_id'])
                    ->store_id;
            },
            'pos_session_id' => function (array $attributes): int {
                $integration = PowerOfficeIntegration::query()
                    ->findOrFail($attributes['power_office_integration_id']);

                return PosSession::factory()->create([
                    'store_id' => $integration->store_id,
                    'status' => 'closed',
                    'closed_at' => now(),
                ])->id;
            },
            'status' => PowerOfficeSyncRunStatus::Pending,
            'idempotency_key' => fn (): string => 'poweroffice_z_report_'.Str::uuid()->toString(),
            'request_payload' => null,
            'response_payload' => null,
            'journal_voucher_no' => null,
            'attempts' => 0,
            'started_at' => null,
            'finished_at' => null,
            'error_message' => null,
        ];
    }
}
