<?php

namespace App\Jobs;

use App\Actions\ConnectedCustomers\UpdateConnectedCustomerToStripe;
use App\Models\ConnectedCustomer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncConnectedCustomerToStripeJob implements ShouldQueue
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
        public ConnectedCustomer $customer
    ) {
        // Set queue name
        $this->onQueue('stripe-sync');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncConnectedCustomerToStripeJob: Starting sync', [
            'customer_id' => $this->customer->id,
            'stripe_customer_id' => $this->customer->stripe_customer_id,
            'stripe_account_id' => $this->customer->stripe_account_id,
            'customer_name' => $this->customer->name,
            'customer_email' => $this->customer->email,
            'attempt' => $this->attempts(),
        ]);

        try {
            $action = new UpdateConnectedCustomerToStripe();
            $action($this->customer);

            Log::info('SyncConnectedCustomerToStripeJob: Successfully synced customer to Stripe', [
                'customer_id' => $this->customer->id,
                'stripe_customer_id' => $this->customer->stripe_customer_id,
                'stripe_account_id' => $this->customer->stripe_account_id,
            ]);
        } catch (Throwable $e) {
            Log::error('SyncConnectedCustomerToStripeJob: Failed to sync customer to Stripe', [
                'customer_id' => $this->customer->id,
                'stripe_customer_id' => $this->customer->stripe_customer_id,
                'stripe_account_id' => $this->customer->stripe_account_id,
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
        Log::error('SyncConnectedCustomerToStripeJob: Job failed permanently', [
            'customer_id' => $this->customer->id,
            'stripe_customer_id' => $this->customer->stripe_customer_id,
            'stripe_account_id' => $this->customer->stripe_account_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

