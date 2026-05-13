<?php

namespace App\Services\Tripletex;

use App\Actions\Stripe\SyncStoreStripeBalanceTransactionsFromStripe;
use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;

final class TripletexPayoutBalanceTransactionHydrator
{
    public function __construct(
        protected SyncStoreStripeBalanceTransactionsFromStripe $syncBalanceTransactions,
    ) {}

    /**
     * Ensure payout voucher building has a payout-scoped balance transaction mirror to work from.
     *
     * @return array{attempted: bool, reason: string, result?: array{total: int, created: int, updated: int, errors: list<string>}}
     */
    public function hydrateIfMissing(Store $store, StoreStripePayout $payout): array
    {
        $stripePayoutId = (string) $payout->stripe_payout_id;
        if (! str_starts_with($stripePayoutId, 'po_')) {
            return [
                'attempted' => false,
                'reason' => 'missing_stripe_payout_id',
            ];
        }

        $usableRows = StoreStripeBalanceTransaction::query()
            ->where('store_id', $store->getKey())
            ->where('stripe_payout_id', $stripePayoutId)
            ->whereIn('type', ['charge', 'payment'])
            ->whereNotNull('stripe_charge_id')
            ->count();

        if ($usableRows > 0) {
            return [
                'attempted' => false,
                'reason' => 'usable_rows_already_present',
            ];
        }

        return [
            'attempted' => true,
            'reason' => 'missing_usable_sale_source_rows',
            'result' => ($this->syncBalanceTransactions)($store, false, $stripePayoutId),
        ];
    }
}
