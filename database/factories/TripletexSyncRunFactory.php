<?php

namespace Database\Factories;

use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Models\PosSession;
use App\Models\TripletexIntegration;
use App\Models\TripletexSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TripletexSyncRun>
 */
class TripletexSyncRunFactory extends Factory
{
    protected $model = TripletexSyncRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tripletex_integration_id' => TripletexIntegration::factory(),
            'store_id' => function (array $attributes): int {
                return TripletexIntegration::query()
                    ->findOrFail($attributes['tripletex_integration_id'])
                    ->store_id;
            },
            'sync_type' => TripletexSyncType::ZReport,
            'pos_session_id' => function (array $attributes): int {
                $integration = TripletexIntegration::query()
                    ->findOrFail($attributes['tripletex_integration_id']);

                return PosSession::factory()->create([
                    'store_id' => $integration->store_id,
                    'status' => 'closed',
                    'closed_at' => now(),
                ])->id;
            },
            'store_stripe_payout_id' => null,
            'status' => TripletexSyncRunStatus::Pending,
            'idempotency_key' => fn (): string => 'tripletex_z_report_'.Str::uuid()->toString(),
            'request_payload' => null,
            'response_payload' => null,
            'tripletex_voucher_id' => null,
            'attempts' => 0,
            'started_at' => null,
            'finished_at' => null,
            'error_message' => null,
        ];
    }
}
