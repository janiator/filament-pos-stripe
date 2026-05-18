<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when Stripe Connect rejects platform access to this store's connected account ID
 * (for example stale `stripe_account_id`, disconnected Standard account, or wrong Stripe keys/app).
 */
final class StripeConnectedAccountInaccessible extends Exception
{
    public const USER_MESSAGE = 'Stripe is not reachable for this store\'s linked account on this integration. Disconnect and reconnect Stripe for this store, or verify the Stripe account is still linked to this platform.';

    private function __construct(
        string $message,
        private readonly string $validationKey,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  non-empty-string  $validationKey
     */
    public static function generic(string $validationKey = 'stripe_account'): self
    {
        return new self(self::USER_MESSAGE, $validationKey);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function validationErrors(): array
    {
        return [
            $this->validationKey => [$this->getMessage()],
        ];
    }

    /**
     * Exposed only for callers that need structured column keys (FlutterFlow / Laravel validation payloads).
     */
    public function fieldKey(): string
    {
        return $this->validationKey;
    }
}
