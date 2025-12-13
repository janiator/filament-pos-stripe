<?php

namespace App\Jobs;

use App\Actions\Stores\SyncStoresFromStripe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStoresFromStripeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting sync stores from Stripe job');

        try {
            $syncAction = new SyncStoresFromStripe();
            $result = $syncAction(false); // Don't send notifications from job

            Log::info('Sync stores from Stripe completed', [
                'total' => $result['total'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Sync stores from Stripe job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
