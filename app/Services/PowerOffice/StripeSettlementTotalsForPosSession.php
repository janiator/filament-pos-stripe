<?php

namespace App\Services\PowerOffice;

use App\Models\PosSession;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\StoreStripePayout;

class StripeSettlementTotalsForPosSession
{
    /**
     * Sum Stripe processing fees (minor units) for balance transactions of type {@code charge}
     * linked to this session's succeeded charges.
     */
    public function feesMinorForSession(PosSession $session): int
    {
        $chargeIds = $session->charges()
            ->where('status', 'succeeded')
            ->pluck('stripe_charge_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($chargeIds === []) {
            return 0;
        }

        return (int) StoreStripeBalanceTransaction::query()
            ->where('store_id', $session->store_id)
            ->where('type', 'charge')
            ->whereIn('stripe_charge_id', $chargeIds)
            ->sum('fee');
    }

    /**
     * Sum paid payouts (minor units) whose bank arrival date falls on the same calendar day
     * as the session close in the application timezone.
     */
    public function payoutMinorForSessionCloseDate(PosSession $session): int
    {
        $closedAt = $session->closed_at ?? now();
        $day = $closedAt->copy()->timezone(config('app.timezone'))->toDateString();

        return (int) StoreStripePayout::query()
            ->where('store_id', $session->store_id)
            ->where('status', 'paid')
            ->whereDate('arrival_date', $day)
            ->sum('amount');
    }
}
