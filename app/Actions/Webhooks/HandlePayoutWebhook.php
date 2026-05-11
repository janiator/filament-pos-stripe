<?php

namespace App\Actions\Webhooks;

use App\Actions\Stripe\SyncStoreStripePayoutsFromStripe;
use App\Models\Store;
use Stripe\Payout;

class HandlePayoutWebhook
{
    public function __construct(
        private SyncStoreStripePayoutsFromStripe $syncStoreStripePayoutsFromStripe,
    ) {}

    public function handle(Payout $payout, string $eventType, ?string $accountId = null): void
    {
        if (! $accountId) {
            \Log::warning('Payout webhook received but no Connect account ID on event', [
                'payout_id' => $payout->id,
                'event_type' => $eventType,
            ]);

            return;
        }

        $store = Store::query()->where('stripe_account_id', $accountId)->first();

        if (! $store) {
            \Log::warning('Payout webhook received but no store for Stripe account', [
                'payout_id' => $payout->id,
                'account_id' => $accountId,
                'event_type' => $eventType,
            ]);

            return;
        }

        if (! $store->hasStripeAccount()) {
            return;
        }

        $this->syncStoreStripePayoutsFromStripe->upsertSinglePayout($store, $payout);
    }
}
