<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tripletex API v2 base URLs
    |--------------------------------------------------------------------------
    */
    'base_urls' => [
        'test' => trim((string) env('TRIPLETEX_TEST_BASE_URL')) ?: 'https://api.tripletex.io/v2',
        'prod' => trim((string) env('TRIPLETEX_PROD_BASE_URL')) ?: 'https://tripletex.no/v2',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('TRIPLETEX_HTTP_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Period preview (Filament / queue job)
    |--------------------------------------------------------------------------
    |
    | Stored JSON is capped so DB writes and Livewire hydration stay reliable
    | when "resolve Tripletex accounts" embeds draft voucher payloads.
    |
    */
    'period_preview' => [
        'max_stored_json_bytes' => (int) env('TRIPLETEX_PERIOD_PREVIEW_MAX_STORED_JSON_BYTES', 1_500_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voucher posting
    |--------------------------------------------------------------------------
    |
    | Tripletex ledger voucher endpoint (draft by default).
    |
    */
    'voucher' => [
        'post_path' => trim((string) env('TRIPLETEX_VOUCHER_POST_PATH')) ?: '/ledger/voucher',
        'send_to_ledger' => filter_var(env('TRIPLETEX_SEND_TO_LEDGER', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session token
    |--------------------------------------------------------------------------
    */
    'session' => [
        'create_path' => trim((string) env('TRIPLETEX_SESSION_CREATE_PATH')) ?: '/token/session/:create',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Filament form values (Merano legacy script)
    |--------------------------------------------------------------------------
    |
    | Mirrors ACCOUNT + clearing behaviour from Merano-Tripletex-Sync/config.js
    | and main.js (getClearingAccount, fee pairings, payout bank line, web-only tickets §7f).
    | Merged only when the integration has no account_mappings rows and no
    | ledger.payout routing saved yet (see TripletexMeranoLegacyFormDefaults).
    |
    | Web / advance tickets (main.js 7f): SALES_BILLETTER_FORHAND (3200), clearing line uses
    | CLEARING_STRIPE (1901), vatType MVA_NONE (6 in Merano VAT_TYPE; Tripletex id may differ per company).
    |
    | Not in the Node script (set manually if needed): output VAT account, tips,
    | rounding, gift-card liability. Optional script constants omitted here:
    | SALES_PROGRAM (3002), SALES_SOUVENIR (3003), APP_FEE (2400), VISIVO supplier id.
    |
    */
    'default_form_state' => [
        'vat_sales_25' => '3001',
        'vat_sales_15' => '3200',
        'vat_sales_0' => '3201',
        'ledger_shared_cash_account_no' => '1900',
        'ledger_shared_card_clearing_account_no' => '1901',
        'ledger_payment_debit_cash' => '1900',
        'ledger_payment_debit_card' => '1901',
        'ledger_payment_debit_card_present' => '1901',
        'ledger_payment_debit_vipps' => '1902',
        'ledger_payment_debit_mobile' => '1901',
        'ledger_payment_debit_default' => '1901',
        'ledger_interim_liquid_account_no' => '1901',
        'ledger_default_sales_account_no' => '3001',
        'ledger_fee_credit_account_no' => '1901',
        'ledger_fee_debit_account_no' => '7771',
        'ledger_payout_credit_account_no' => '1901',
        'ledger_payout_debit_bank_account_no' => '1920',

        // Merano config.js ACCOUNT + main.js §7f web-only tickets (clearing = CLEARING_STRIPE)
        'ledger_external_ticket_sales_account_no' => '3200',
        'ledger_external_ticket_clearing_account_no' => '1901',
        'ledger_external_ticket_vat_type_id' => '6',
    ],
];
