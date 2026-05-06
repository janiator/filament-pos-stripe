<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Print Receipts
    |--------------------------------------------------------------------------
    |
    | When enabled, receipts will be automatically printed after a successful
    | purchase. Set to false to disable automatic printing.
    |
    | Default is false - printing is managed through the POS frontend.
    |
    */
    'auto_print_receipts' => env('POS_AUTO_PRINT_RECEIPTS', false),

    /*
    |--------------------------------------------------------------------------
    | Cash Drawer Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cash drawer opening behavior.
    |
    */
    'cash_drawer' => [
        'auto_open' => env('POS_CASH_DRAWER_AUTO_OPEN', true),
        'open_duration_ms' => env('POS_CASH_DRAWER_OPEN_DURATION_MS', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily auto-close of open POS sessions
    |--------------------------------------------------------------------------
    |
    | When enabled, the scheduler runs `pos:auto-close-open-sessions` once per
    | day (see POS_AUTO_CLOSE_SESSIONS_TIME, app timezone). Only stores with
    | "Auto-close open sessions daily" turned on in Settings are processed.
    | Sessions are closed with actual cash set to expected cash. Use
    | `pos:auto-close-open-sessions --force` to close every open session
    | (ignores this env flag and the per-store toggle).
    |
    */
    'auto_close_sessions' => [
        'enabled' => env('POS_AUTO_CLOSE_SESSIONS_DAILY', false),
        'time' => env('POS_AUTO_CLOSE_SESSIONS_TIME', '00:30'),
        'closing_notes' => env(
            'POS_AUTO_CLOSE_SESSIONS_NOTES',
            'Session auto-closed by daily schedule.'
        ),
    ],
];
