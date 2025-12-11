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
    */

    'events' => [
        // Customer events (core Connect events)
        'customer.updated',
        'customer.deleted',
        
        // Subscription events (core Connect events)
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        
        // Charge events (core Connect events)
        'charge.succeeded',
        
        // Invoice events (for subscriptions - core Connect events)
        'invoice.payment_action_required',
        'invoice.payment_succeeded',
        
        // Additional events that may be valid for Connect
        'customer.created',
        'customer.subscription.paused',
        'customer.subscription.resumed',
        'customer.subscription.trial_will_end',
        'transfer.created',
        'transfer.updated',
        'transfer.reversed',
    ],
];

