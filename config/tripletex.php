<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tripletex API
    |--------------------------------------------------------------------------
    | Sync Z-reports to Tripletex (same pattern as Zettle+Tripletex: voucher
    | per day/session, clearing accounts per payment type). API: token/session
    | + ledger/voucher. See https://api.tripletex.io/v2/docs/
    */
    'test_mode' => env('TRIPLETEX_TEST_MODE', false),

    'api_base_url' => env('TRIPLETEX_TEST_MODE', false)
        ? 'https://api.tripletex.io/v2'
        : (env('TRIPLETEX_API_BASE_URL') ?: 'https://tripletex.no/v2'),

    'consumer_token' => env('TRIPLETEX_TEST_MODE', false)
        ? env('TRIPLETEX_TEST_CONSUMER_TOKEN')
        : env('TRIPLETEX_CONSUMER_TOKEN'),

    'employee_token' => env('TRIPLETEX_TEST_MODE', false)
        ? env('TRIPLETEX_TEST_EMPLOYEE_TOKEN')
        : env('TRIPLETEX_EMPLOYEE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Ledger account numbers (Tripletex)
    |--------------------------------------------------------------------------
    | Map payment types to your chart of accounts. Adjust to match your
    | Tripletex setup (same idea as Merano-Tripletex-Sync config.js).
    */
    'accounts' => [
        'sales' => (int) env('TRIPLETEX_ACCOUNT_SALES', 3000),
        'clearing_stripe' => (int) env('TRIPLETEX_ACCOUNT_CLEARING_STRIPE', 1901),
        'clearing_vipps' => (int) env('TRIPLETEX_ACCOUNT_CLEARING_VIPPS', 1902),
        'clearing_cash' => (int) env('TRIPLETEX_ACCOUNT_CLEARING_CASH', 1900),
        'clearing_other' => (int) env('TRIPLETEX_ACCOUNT_CLEARING_OTHER', 1901),
    ],

    'vat_type_id' => (int) env('TRIPLETEX_VAT_TYPE_ID', 31), // 25% MVA
];
