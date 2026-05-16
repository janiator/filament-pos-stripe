<?php

namespace App\Exceptions;

use Stripe\Exception\PermissionException;

class StripeConnectedAccountInaccessible extends \RuntimeException
{
    public static function fromPermissionException(PermissionException $previous): self
    {
        return new self(
            'This store\'s Stripe account is not accessible with the current platform credentials. Reconnect Stripe for this store or contact support.',
            0,
            $previous
        );
    }
}
