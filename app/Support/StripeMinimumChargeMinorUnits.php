<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Stripe minimum payment amounts per presentment currency, in minor units as required by the API.
 *
 * @see https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts
 */
final class StripeMinimumChargeMinorUnits
{
    /**
     * @var array<string, int> Lowercase ISO 4217 => minimum charge in Stripe API minor units
     */
    private const MIN_MINOR_BY_CURRENCY = [
        'aed' => 200,
        'ars' => 50,
        'aud' => 50,
        'brl' => 50,
        'cad' => 50,
        'chf' => 50,
        'cop' => 50,
        'czk' => 1500,
        'dkk' => 250,
        'eur' => 50,
        'gbp' => 30,
        'hkd' => 400,
        'huf' => 17500,
        'ils' => 50,
        'inr' => 50,
        'jpy' => 50,
        'krw' => 50,
        'mxn' => 1000,
        'myr' => 200,
        'nok' => 300,
        'nzd' => 50,
        'php' => 50,
        'pln' => 200,
        'ron' => 200,
        'rub' => 50,
        'sek' => 300,
        'sgd' => 50,
        'thb' => 1000,
        'usd' => 50,
        'zar' => 50,
    ];

    /**
     * Returns null when Stripe does not document a minimum in {@see self::MIN_MINOR_BY_CURRENCY};
     * the API will enforce its rules for that currency.
     */
    public static function forCurrency(string $currency): ?int
    {
        $code = strtolower($currency);

        return self::MIN_MINOR_BY_CURRENCY[$code] ?? null;
    }

    /**
     * User-facing phrase for validation errors (e.g. "3.00 kr (NOK)").
     */
    public static function describeMinimumCharge(string $currency): ?string
    {
        $minor = self::forCurrency($currency);
        if ($minor === null) {
            return null;
        }

        $codeUpper = strtoupper($currency);

        return match ($codeUpper) {
            'JPY', 'KRW' => number_format($minor, 0, '.', '').' '.$codeUpper,
            default => number_format($minor / 100, 2, '.', '').' '.$codeUpper,
        };
    }
}
