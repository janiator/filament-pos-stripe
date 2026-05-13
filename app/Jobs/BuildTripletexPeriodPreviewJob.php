<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Tripletex\TripletexPeriodPreviewService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BuildTripletexPeriodPreviewJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(
        public int $storeId,
        public string $fromDate,
        public string $toDate,
        public bool $resolveTripletexAccounts,
        public int $maxZReports,
        public int $maxPayouts,
        public bool $detailedPreviews,
    ) {}

    public function uniqueId(): string
    {
        return 'tripletex-period-preview-store-'.$this->storeId;
    }

    public static function cacheKeyFor(int $storeId): string
    {
        return 'tripletex:period_preview:'.$storeId;
    }

    public function handle(TripletexPeriodPreviewService $periodPreview): void
    {
        $key = self::cacheKeyFor($this->storeId);

        Cache::put($key, [
            'status' => 'processing',
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(2));

        try {
            $store = Store::query()->with('tripletexIntegration')->findOrFail($this->storeId);
            $integration = $store->tripletexIntegration;
            if (! $integration || ! $integration->isConnected()) {
                throw new \RuntimeException('Tripletex is not connected for this store.');
            }

            $payload = $periodPreview->previewPeriod(
                $store,
                $integration,
                Carbon::parse($this->fromDate)->startOfDay(),
                Carbon::parse($this->toDate)->endOfDay(),
                $this->resolveTripletexAccounts,
                $this->maxZReports,
                $this->maxPayouts,
                $this->detailedPreviews,
            );

            Cache::put($key, [
                'status' => 'complete',
                'result' => $payload,
                'updated_at' => now()->toIso8601String(),
            ], now()->addHours(2));
        } catch (Throwable $e) {
            Log::error('Tripletex period preview job failed', [
                'store_id' => $this->storeId,
                'message' => $e->getMessage(),
            ]);

            Cache::put($key, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'updated_at' => now()->toIso8601String(),
            ], now()->addHours(2));

            throw $e;
        }
    }
}
