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
        'account.created',
        'account.updated',
        'account.deleted',
        
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
        
        // Payment method events
        'payment_method.attached',
        'payment_method.detached',
        'payment_method.updated',
        'payment_method.automatically_updated',
        
        // Payment link events
        'checkout.session.completed',
        'payment_link.created',
        'payment_link.updated',
        
        // Transfer events
        'transfer.created',
        'transfer.updated',
        'transfer.reversed',
        'transfer.paid',
        'transfer.failed',
        
        // Terminal location events
        'terminal.location.created',
        'terminal.location.updated',
        'terminal.location.deleted',
        
        // Invoice events (for subscriptions)
        'invoice.created',
        'invoice.finalized',
        'invoice.payment_succeeded',
        'invoice.payment_failed',
        'invoice.updated',
    ],
];

