<?php

namespace App\Services\Tripletex;

use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Models\TripletexIntegration;
use App\Models\TripletexSyncRun;
use Illuminate\Support\Facades\Log;

final class TripletexVoucherSyncSkipRecorder
{
    public function hasSuccessfulRun(string $idempotencyKey): bool
    {
        return TripletexSyncRun::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', TripletexSyncRunStatus::Success)
            ->exists();
    }

    /**
     * Persist a skipped voucher attempt when integration exists. Does not downgrade
     * failed or in-flight runs so retries and diagnostics stay intact.
     */
    public function record(
        TripletexIntegration $integration,
        int $storeId,
        string $idempotencyKey,
        TripletexSyncType $syncType,
        ?int $posSessionId,
        ?int $storeStripePayoutId,
        string $reason,
    ): void {
        if ($this->hasSuccessfulRun($idempotencyKey)) {
            return;
        }

        $syncRun = TripletexSyncRun::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'tripletex_integration_id' => $integration->getKey(),
                'store_id' => $storeId,
                'sync_type' => $syncType,
                'pos_session_id' => $posSessionId,
                'store_stripe_payout_id' => $storeStripePayoutId,
                'status' => TripletexSyncRunStatus::Pending,
            ],
        );

        if ($syncRun->status === TripletexSyncRunStatus::Success) {
            return;
        }

        if (in_array($syncRun->status, [TripletexSyncRunStatus::Failed, TripletexSyncRunStatus::Processing], true)) {
            Log::info('Tripletex voucher sync skipped (not recorded on sync run)', [
                'tripletex_sync_run_id' => $syncRun->id,
                'existing_status' => $syncRun->status->value,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ]);

            return;
        }

        $syncRun->status = TripletexSyncRunStatus::Skipped;
        $syncRun->finished_at = now();
        $syncRun->error_message = $reason;
        $syncRun->started_at = null;
        $syncRun->save();

        Log::info('Tripletex voucher sync skipped', [
            'tripletex_sync_run_id' => $syncRun->id,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
            'sync_type' => $syncType->value,
            'store_id' => $storeId,
        ]);
    }
}
