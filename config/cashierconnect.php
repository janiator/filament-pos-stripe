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
        
        // Subscription item events
        'customer.subscription.item.created',
        'customer.subscription.item.updated',
        'customer.subscription.item.deleted',
        
        // Product events
        'product.created',
        'product.updated',
        'product.deleted',
        
        // Price events
        'price.created',
        'price.updated',
        'price.deleted',
        
        // Charge events
        'charge.created',
        'charge.updated',
        'charge.refunded',
        'charge.refund.updated',
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
        'transfer.paid',
        'transfer.failed',
        
        // Invoice events (for subscriptions)
        'invoice.payment_action_required',
        'invoice.payment_succeeded',
    ],
];

