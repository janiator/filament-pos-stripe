<?php

namespace App\Support\Stripe;

use Stripe\StripeObject;

/**
 * Converts Stripe {@see StripeObject} metadata to a plain associative array for Eloquent JSON columns.
 * Casting with {@see (array)} on SDK objects pulls PHP internal properties (garbage keys); use {@see StripeObject::toArray()} instead.
 */
final class StripeMetadata
{
    /**
     * @return array<string, mixed>|null
     */
    public static function toArray(mixed $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        if (is_array($metadata)) {
            return $metadata === [] ? null : $metadata;
        }

        if ($metadata instanceof StripeObject) {
            $arr = $metadata->toArray();
            if (! is_array($arr) || $arr === []) {
                return null;
            }

            return $arr;
        }

        return null;
    }
}
