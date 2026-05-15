<?php

namespace App\Observers;

use App\Enums\AddonType;
use App\Jobs\SyncTripletexPayoutJob;
use App\Models\Addon;
use App\Models\StoreStripePayout;

class StoreStripePayoutObserver
{
    /**
     * Queue Tripletex payout voucher when a Stripe payout is recorded as paid.
     */
    public function saved(StoreStripePayout $payout): void
    {
        if ($payout->status !== 'paid') {
            return;
        }

        if (! $payout->wasRecentlyCreated && ! $payout->wasChanged('status')) {
            return;
        }

        $store = $payout->store;
        if (! $store || ! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            return;
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected() || ! $integration->sync_enabled || ! $integration->auto_sync_payouts) {
            return;
        }

        SyncTripletexPayoutJob::dispatch(
            $payout->id,
            false,
            (bool) $integration->skip_payout_bank_transfer,
        );
    }
}
