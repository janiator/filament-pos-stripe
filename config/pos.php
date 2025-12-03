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
    */
    'auto_print_receipts' => env('POS_AUTO_PRINT_RECEIPTS', true),

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
];


