<?php

namespace App\Jobs;

use App\Actions\TerminalLocations\UpdateTerminalLocationToStripe;
use App\Models\TerminalLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncTerminalLocationToStripeJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(
        public TerminalLocation $location
    ) {
        // Set queue name
        $this->onQueue('stripe-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncTerminalLocationToStripeJob: Starting sync', [
            'location_id' => $this->location->id,
            'stripe_location_id' => $this->location->stripe_location_id,
            'display_name' => $this->location->display_name,
            'store_id' => $this->location->store_id,
            'attempt' => $this->attempts(),
        ]);

        try {
            $action = new UpdateTerminalLocationToStripe();
            $action($this->location);

            Log::info('SyncTerminalLocationToStripeJob: Successfully synced terminal location to Stripe', [
                'location_id' => $this->location->id,
                'stripe_location_id' => $this->location->stripe_location_id,
            ]);
        } catch (Throwable $e) {
            Log::error('SyncTerminalLocationToStripeJob: Failed to sync terminal location to Stripe', [
                'location_id' => $this->location->id,
                'stripe_location_id' => $this->location->stripe_location_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SyncTerminalLocationToStripeJob: Job failed permanently', [
            'location_id' => $this->location->id,
            'stripe_location_id' => $this->location->stripe_location_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

