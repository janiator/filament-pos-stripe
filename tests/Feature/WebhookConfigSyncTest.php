<?php

/**
 * Ensures Stripe Connect webhook config and controller stay in sync.
 * - Every event in config must be handled by the controller.
 * - Every event we intend to register (below) must be present in config.
 */
beforeEach(function () {
    $this->configEvents = config('cashierconnect.events');
});

/**
 * Event types handled in StripeConnectWebhookController switch (all case branches).
 * If you add a new case in the controller, add it here and to EXPECTED_REGISTERED_EVENTS.
 */
const CONTROLLER_HANDLED_EVENTS = [
    'account.created',
    'account.updated',
    'account.deleted',
    'customer.created',
    'customer.updated',
    'customer.deleted',
    'customer.subscription.created',
    'customer.subscription.updated',
    'customer.subscription.deleted',
    'customer.subscription.paused',
    'customer.subscription.resumed',
    'customer.subscription.trial_will_end',
    'product.created',
    'product.updated',
    'product.deleted',
    'price.created',
    'price.updated',
    'price.deleted',
    'charge.created',
    'charge.updated',
    'charge.succeeded',
    'charge.pending',
    'charge.failed',
    'charge.captured',
    'charge.refunded',
    'charge.refund.updated',
    'payment_method.attached',
    'payment_method.detached',
    'payment_method.updated',
    'payment_method.automatically_updated',
    'payment_link.created',
    'payment_link.updated',
    'transfer.created',
    'transfer.updated',
    'transfer.reversed',
    'transfer.paid',
    'transfer.failed',
];

/**
 * Events we register with Stripe (subset of controller-handled; e.g. we omit account.created/deleted by design).
 * Must match config/cashierconnect.php. If you add an event here, add it to the config file too.
 */
const EXPECTED_REGISTERED_EVENTS = [
    'account.updated',
    'customer.created',
    'customer.updated',
    'customer.deleted',
    'customer.subscription.created',
    'customer.subscription.updated',
    'customer.subscription.deleted',
    'customer.subscription.paused',
    'customer.subscription.resumed',
    'customer.subscription.trial_will_end',
    'product.created',
    'product.updated',
    'product.deleted',
    'price.created',
    'price.updated',
    'price.deleted',
    'charge.succeeded',
    'payment_method.attached',
    'payment_method.detached',
    'payment_method.updated',
    'payment_method.automatically_updated',
    'payment_link.created',
    'payment_link.updated',
    'transfer.created',
    'transfer.updated',
    'transfer.reversed',
];

it('has every config event handled by the controller', function () {
    $handledSet = array_flip(CONTROLLER_HANDLED_EVENTS);
    $missing = [];
    foreach ($this->configEvents as $event) {
        if (! isset($handledSet[$event])) {
            $missing[] = $event;
        }
    }
    expect($missing)->toBeEmpty(
        'These events are in cashierconnect.events but not handled in StripeConnectWebhookController: '.implode(', ', $missing)
    );
});

it('registers exactly the expected events with Stripe', function () {
    $configSet = collect($this->configEvents)->sort()->values()->all();
    $expectedSet = collect(EXPECTED_REGISTERED_EVENTS)->sort()->values()->all();
    expect($configSet)->toEqual($expectedSet)
        ->and($this->configEvents)->toHaveCount(count(EXPECTED_REGISTERED_EVENTS));
});
