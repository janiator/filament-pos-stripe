<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe balance transactions (Connect mirror)
    |--------------------------------------------------------------------------
    |
    | When enabled, the Laravel scheduler dispatches one queued job per store
    | that has a Stripe Connect account id, so balance transactions (charges,
    | fees, payout rows, py_/ch_ source ids) stay mirrored without manual clicks.
    |
    */

    'balance_transactions' => [
        'schedule_enabled' => env('STRIPE_SYNC_BALANCE_TRANSACTIONS_SCHEDULE', true),
        /** Seconds subtracted from the newest mirrored stripe_created when requesting incremental Stripe pages (scheduled / full-store sync only). */
        'incremental_overlap_seconds' => (int) env('STRIPE_SYNC_BALANCE_TRANSACTIONS_OVERLAP_SECONDS', 86_400),
    ],

];
