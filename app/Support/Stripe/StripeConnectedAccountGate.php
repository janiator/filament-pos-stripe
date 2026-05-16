<?php

namespace App\Support\Stripe;

use App\Exceptions\StripeConnectedAccountInaccessible;
use Stripe\Exception\PermissionException;
use Stripe\StripeClient;

final class StripeConnectedAccountGate
{
    /**
     * Ensure the platform secret key may act on $stripeConnectedAccountId before making Connect requests
     * (Stripe-Account / stripe_account header).
     *
     * @throws StripeConnectedAccountInaccessible When this platform cannot manage the linked connected account ID
     * @throws PermissionException When Stripe reports unrelated permission denial
     */
    public static function assertPlatformMayAccessConnectedAccount(
        StripeClient $stripe,
        ?string $stripeConnectedAccountId,
        string $messageKey = 'stripe_account'
    ): void {
        if ($stripeConnectedAccountId === null || trim($stripeConnectedAccountId) === '') {
            throw StripeConnectedAccountInaccessible::generic($messageKey);
        }

        try {
            $stripe->accounts->retrieve($stripeConnectedAccountId);
        } catch (PermissionException $exception) {
            self::throwAsInaccessibleStripeConnectUnlessUnrelated($exception, $messageKey);
        }
    }

    /**
     * Normalize Stripe PermissionException failures on Connect-context calls where the Stripe-Account is invalid/disconnected.
     *
     * @throws StripeConnectedAccountInaccessible
     */
    public static function raiseIfDisconnectedConnectedAccountStripeCall(
        PermissionException $exception,
        string $messageKey = 'stripe_account'
    ): void {
        self::throwAsInaccessibleStripeConnectUnlessUnrelated($exception, $messageKey);
    }

    protected static function throwAsInaccessibleStripeConnectUnlessUnrelated(
        PermissionException $exception,
        string $messageKey
    ): void {
        $normalized = strtolower($exception->getMessage());

        $matchesInaccessibleConnectedAccount = str_contains($normalized, 'does not have access')
            && str_contains($normalized, 'connected account');

        $matchesOnBehalfWording = str_contains($normalized, 'cannot be used to perform requests on behalf')
            || str_contains($normalized, "can't be used to perform requests");

        $matchesStripeAccountHeaderIssue = str_contains($normalized, 'stripe-account header');

        if ($matchesInaccessibleConnectedAccount || $matchesOnBehalfWording || $matchesStripeAccountHeaderIssue) {
            throw StripeConnectedAccountInaccessible::generic($messageKey);
        }

        throw $exception;
    }
}
