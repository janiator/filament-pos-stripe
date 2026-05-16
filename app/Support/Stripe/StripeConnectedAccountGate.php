<?php

namespace App\Support\Stripe;

use App\Exceptions\StripeConnectedAccountInaccessible;
use Stripe\Exception\PermissionException;
use Stripe\StripeClient;

final class StripeConnectedAccountGate
{
    public static function assertPlatformMayUseConnectedAccount(StripeClient $stripe, string $stripeAccountId): void
    {
        if ($stripeAccountId === '') {
            throw new StripeConnectedAccountInaccessible('This store has no Stripe connected account.');
        }

        try {
            $stripe->accounts->retrieve($stripeAccountId);
        } catch (PermissionException $e) {
            throw StripeConnectedAccountInaccessible::fromPermissionException($e);
        }
    }
}
