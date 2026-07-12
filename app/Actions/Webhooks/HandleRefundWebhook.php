<?php

namespace App\Actions\Webhooks;

use Stripe\Charge;
use Stripe\Refund;
use Stripe\StripeClient;

/**
 * Stripe sends a Refund object for charge.refund.updated. We retrieve the updated Charge on the
 * connected account and delegate to HandleChargeWebhook so ConnectedCharge stays in sync.
 */
class HandleRefundWebhook
{
    public function __construct(
        private HandleChargeWebhook $handleChargeWebhook,
    ) {}

    public function handle(Refund $refund, string $eventType, ?string $accountId = null): void
    {
        $chargeId = $refund->charge;

        if (! is_string($chargeId) || $chargeId === '') {
            \Log::warning('Stripe refund webhook missing charge id', [
                'refund_id' => $refund->id ?? null,
                'event_type' => $eventType,
            ]);

            return;
        }

        $charge = $this->retrieveChargeFromStripe($chargeId, $accountId);

        if ($charge === null) {
            return;
        }

        $this->handleChargeWebhook->handle($charge, $eventType, $accountId);
    }

    protected function retrieveChargeFromStripe(string $chargeId, ?string $accountId): ?Charge
    {
        $secret = config('cashier.secret') ?? config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            \Log::error('Stripe secret not configured for refund webhook charge retrieval');

            return null;
        }

        $stripe = new StripeClient($secret);

        $requestOptions = [];

        if ($accountId !== null && $accountId !== '') {
            $requestOptions['stripe_account'] = $accountId;
        }

        try {
            $charge = $stripe->charges->retrieve($chargeId, [], $requestOptions);
        } catch (\Throwable $e) {
            \Log::error('Failed to retrieve Stripe charge for refund webhook', [
                'charge_id' => $chargeId,
                'stripe_account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $charge instanceof Charge) {
            \Log::warning('Stripe refund webhook charge retrieval returned unexpected type', [
                'charge_id' => $chargeId,
                'type' => get_debug_type($charge),
            ]);

            return null;
        }

        return $charge;
    }
}
