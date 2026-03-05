<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webflow CMS item edit page class
    |--------------------------------------------------------------------------
    | Override with an app page class to add app-specific behaviour (e.g. event
    | ticket section when collection has use_for_event_tickets).
    */
    'item_edit_page' => \Positiv\FilamentWebflow\Filament\Pages\WebflowItemEditPage::class,

    /*
    |--------------------------------------------------------------------------
    | Webflow API base URL
    |--------------------------------------------------------------------------
    */
    'api_base_url' => env('WEBFLOW_API_BASE_URL', 'https://api.webflow.com/v2/'),

    /*
    |--------------------------------------------------------------------------
    | Default API token (optional - can be set per site)
    |--------------------------------------------------------------------------
    */
    'api_token' => env('WEBFLOW_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Sync settings
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'pull_batch_size' => 100,
        'push_queue' => env('WEBFLOW_PUSH_QUEUE', 'default'),
        'pull_queue' => env('WEBFLOW_PULL_QUEUE', 'default'),
    ],
];
