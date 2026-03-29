<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API base URLs (PowerOffice Go API v2)
    |--------------------------------------------------------------------------
    |
    | See: https://developer.poweroffice.net/documentation/from-v1-to-v2
    | V2 base endpoints: demo …/demo/v2, prod …/v2. Paths below are appended.
    |
    */
    'base_urls' => [
        // Use `?:` so an empty .env value does not override the default (avoids relative URLs / cURL error 3).
        'dev' => trim((string) env('POWEROFFICE_DEMO_BASE_URL')) ?: 'https://goapi.poweroffice.net/demo/v2',
        'prod' => trim((string) env('POWEROFFICE_PROD_BASE_URL')) ?: 'https://goapi.poweroffice.net/v2',
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth token URLs (v2 only — separate from REST base above)
    |--------------------------------------------------------------------------
    |
    | Demo uses a capital "Demo" segment; prod uses lowercase path.
    | Include POWEROFFICE_SUBSCRIPTION_KEY in token requests per PowerOffice v2.
    |
    */
    'oauth' => [
        'token_url' => [
            'dev' => trim((string) env('POWEROFFICE_DEMO_OAUTH_TOKEN_URL')) ?: 'https://goapi.poweroffice.net/Demo/OAuth/Token',
            'prod' => trim((string) env('POWEROFFICE_PROD_OAUTH_TOKEN_URL')) ?: 'https://goapi.poweroffice.net/oauth/token',
        ],
    ],

    'subscription_key' => env('POWEROFFICE_SUBSCRIPTION_KEY'),

    'client_id' => env('POWEROFFICE_CLIENT_ID'),
    'client_secret' => env('POWEROFFICE_CLIENT_SECRET'),

    'callback_secret' => env('POWEROFFICE_CALLBACK_SECRET'),

    'onboarding' => [
        // v2 OpenAPI: POST …/Onboarding/Initiate and …/Onboarding/Finalize (see developer.poweroffice.net Swagger "Onboarding")
        'init_path' => trim((string) env('POWEROFFICE_ONBOARDING_INIT_PATH')) ?: '/Onboarding/Initiate',
        'complete_path' => trim((string) env('POWEROFFICE_ONBOARDING_COMPLETE_PATH')) ?: '/Onboarding/Finalize',
    ],

    'ledger' => [
        // Default: direct manual journal posting (/Vouchers/ManualJournals) — matches Go UI "Direktepostere manuelle bilag".
        // Use /JournalEntryVouchers/ManualJournals if the client enabled "send to journal entry" / Journal Entry Voucher workflow only.
        'post_path' => trim((string) env('POWEROFFICE_LEDGER_POST_PATH')) ?: '/Vouchers/ManualJournals',
    ],

    /*
    |--------------------------------------------------------------------------
    | Posted voucher documentation (PDF)
    |--------------------------------------------------------------------------
    |
    | PUT multipart upload attaches a PDF to a voucher created via the API.
    | Requires VoucherDocumentation_Full on the integration (PowerOffice Go).
    |
    */
    'voucher_documentation' => [
        'put_path' => trim((string) env('POWEROFFICE_VOUCHER_DOCUMENTATION_PATH')) ?: '/VoucherDocumentation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Diagnostics (optional)
    |--------------------------------------------------------------------------
    |
    | GET path for Client Integration Information (privilege / subscription debug).
    | Leave empty to try common paths; override if your Swagger uses a different route.
    |
    */
    'diagnostics' => [
        'client_integration_information_path' => trim((string) env('POWEROFFICE_CLIENT_INTEGRATION_INFO_PATH')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public URLs (whitelist with PowerOffice for demo + production)
    |--------------------------------------------------------------------------
    */
    'urls' => [
        'callback' => env('POWEROFFICE_CALLBACK_URL'),
        'redirect' => env('POWEROFFICE_REDIRECT_URL'),
    ],

];
