<?php

namespace App\Jobs;

use App\Actions\ConnectedSubscriptions\UpdateConnectedSubscriptionToStripe;
use App\Models\ConnectedSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncConnectedSubscriptionToStripeJob implements ShouldQueue
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
        public ConnectedSubscription $subscription
    ) {
        // Set queue name
        $this->onQueue('stripe-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncConnectedSubscriptionToStripeJob: Starting sync', [
            'subscription_id' => $this->subscription->id,
            'stripe_id' => $this->subscription->stripe_id,
            'stripe_account_id' => $this->subscription->stripe_account_id,
            'stripe_customer_id' => $this->subscription->stripe_customer_id,
            'subscription_name' => $this->subscription->name,
            'attempt' => $this->attempts(),
        ]);

        try {
            $action = new UpdateConnectedSubscriptionToStripe();
            $action($this->subscription);

            Log::info('SyncConnectedSubscriptionToStripeJob: Successfully synced subscription to Stripe', [
                'subscription_id' => $this->subscription->id,
                'stripe_id' => $this->subscription->stripe_id,
                'stripe_account_id' => $this->subscription->stripe_account_id,
            ]);
        } catch (Throwable $e) {
            Log::error('SyncConnectedSubscriptionToStripeJob: Failed to sync subscription to Stripe', [
                'subscription_id' => $this->subscription->id,
                'stripe_id' => $this->subscription->stripe_id,
                'stripe_account_id' => $this->subscription->stripe_account_id,
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
        Log::error('SyncConnectedSubscriptionToStripeJob: Job failed permanently', [
            'subscription_id' => $this->subscription->id,
            'stripe_id' => $this->subscription->stripe_id,
            'stripe_account_id' => $this->subscription->stripe_account_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

