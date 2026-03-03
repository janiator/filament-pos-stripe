<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Connect Webhook Events
    |--------------------------------------------------------------------------
    |
    | This array contains all the Stripe webhook events that should be
    | registered when running `php artisan connect:webhook`. These events
    | will be sent to your webhook endpoint for processing.
    |
    | After changing this config, re-register the webhook so Stripe sends
    | these events: either run `php artisan connect:webhook --url=<APP_URL>/api/stripe/connect/webhook`
    | or in Stripe Dashboard: Developers > Webhooks > your Connect endpoint > add events.
    |
    */

    'events' => [
        // Account events
        'account.updated',

        // Customer events
        'customer.created',
        'customer.updated',
        'customer.deleted',

        // Subscription events
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'customer.subscription.paused',
        'customer.subscription.resumed',
        'customer.subscription.trial_will_end',

        // Product events
        'product.created',
        'product.updated',
        'product.deleted',

        // Price events
        'price.created',
        'price.updated',
        'price.deleted',

        // Charge events
        'charge.succeeded',

        // Payment method events
        'payment_method.attached',
        'payment_method.detached',
        'payment_method.updated',
        'payment_method.automatically_updated',

        // Payment link events
        'payment_link.created',
        'payment_link.updated',

        // Transfer events
        'transfer.created',
        'transfer.updated',
        'transfer.reversed',
    ],
];

