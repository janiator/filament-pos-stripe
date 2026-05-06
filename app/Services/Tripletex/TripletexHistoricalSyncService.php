<?php

namespace App\Services\Tripletex;

use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Jobs\SyncTripletexPayoutJob;
use App\Jobs\SyncTripletexZReportJob;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreStripePayout;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class TripletexHistoricalSyncService
{
    public function __construct(
        protected TripletexZReportSync $zReportSync,
    ) {}

    /**
     * Queue Z-report sync jobs for closed sessions (force), optionally only where no successful Tripletex Z run exists.
     *
     * @return array{queued: int, skipped: int}
     */
    public function queueZReports(
        Store $store,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        int $limit,
        bool $onlyWithoutSuccessfulRun = true,
    ): array {
        $limit = max(1, min($limit, 500));

        $query = PosSession::query()
            ->where('store_id', $store->getKey())
            ->where('status', 'closed')
            ->whereNotNull('closing_data')
            ->when($from, fn (Builder $q): Builder => $q->where('closed_at', '>=', $from))
            ->when($to, fn (Builder $q): Builder => $q->where('closed_at', '<=', $to))
            ->orderByDesc('closed_at')
            ->limit($limit);

        if ($onlyWithoutSuccessfulRun) {
            $query->whereDoesntHave('tripletexSyncRuns', function (Builder $q): void {
                $q->where('sync_type', TripletexSyncType::ZReport)
                    ->where('status', TripletexSyncRunStatus::Success);
            });
        }

        $queued = 0;
        $skipped = 0;

        foreach ($query->get() as $session) {
            if (! $session instanceof PosSession) {
                continue;
            }

            if (! $this->zReportSync->isSessionEligibleForSync($session)) {
                $skipped++;

                continue;
            }

            SyncTripletexZReportJob::dispatch($session->id, true);
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * Queue payout sync jobs for paid Stripe payout rows (force), optionally only where no successful Tripletex payout run exists.
     *
     * @return array{queued: int, skipped: int}
     */
    public function queuePayouts(
        Store $store,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
        int $limit,
        bool $onlyWithoutSuccessfulRun = true,
    ): array {
        $limit = max(1, min($limit, 500));

        $query = StoreStripePayout::query()
            ->where('store_id', $store->getKey())
            ->where('status', 'paid')
            ->when($from, fn (Builder $q): Builder => $q->where('arrival_date', '>=', $from))
            ->when($to, fn (Builder $q): Builder => $q->where('arrival_date', '<=', $to))
            ->orderByDesc('arrival_date')
            ->limit($limit);

        if ($onlyWithoutSuccessfulRun) {
            $query->whereDoesntHave('tripletexSyncRuns', function (Builder $q): void {
                $q->where('sync_type', TripletexSyncType::Payout)
                    ->where('status', TripletexSyncRunStatus::Success);
            });
        }

        $queued = 0;
        foreach ($query->get() as $payout) {
            if (! $payout instanceof StoreStripePayout) {
                continue;
            }
            SyncTripletexPayoutJob::dispatch($payout->id, true);
            $queued++;
        }

        return ['queued' => $queued, 'skipped' => 0];
    }
}
