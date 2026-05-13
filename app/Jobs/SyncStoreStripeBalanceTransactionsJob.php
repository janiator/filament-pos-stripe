<?php

namespace App\Jobs;

use App\Actions\Stripe\SyncStoreStripeBalanceTransactionsFromStripe;
use App\Models\Store;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStoreStripeBalanceTransactionsJob implements ShouldBeUnique, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 1200;

    public int $uniqueFor = 900;

    public function __construct(
        public Store $store,
        public ?string $onlyStripePayoutId = null,
    ) {
        $this->onQueue('stripe-sync');
    }

    public function uniqueId(): string
    {
        if ($this->onlyStripePayoutId !== null && str_starts_with($this->onlyStripePayoutId, 'po_')) {
            return 'sync-store-stripe-balance-tx:'.$this->store->getKey().':'.$this->onlyStripePayoutId;
        }

        return 'sync-store-stripe-balance-tx:'.$this->store->getKey();
    }

    public function handle(SyncStoreStripeBalanceTransactionsFromStripe $sync): void
    {
        Log::info('Starting Stripe balance transaction sync for store', [
            'store_id' => $this->store->getKey(),
            'only_stripe_payout_id' => $this->onlyStripePayoutId,
        ]);

        try {
            $this->store->refresh();

            if (! $this->store->stripe_account_id) {
                Log::warning('Skipping Stripe balance transaction sync — store has no Stripe account id', [
                    'store_id' => $this->store->getKey(),
                ]);

                return;
            }

            $result = $sync($this->store, false, $this->onlyStripePayoutId);

            Log::info('Stripe balance transaction sync finished for store', [
                'store_id' => $this->store->getKey(),
                'total' => $result['total'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors']),
            ]);

            if ($result['errors'] !== []) {
                Log::warning('Stripe balance transaction sync reported row-level errors', [
                    'store_id' => $this->store->getKey(),
                    'errors' => array_slice($result['errors'], 0, 15),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Stripe balance transaction sync job failed for store', [
                'store_id' => $this->store->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
