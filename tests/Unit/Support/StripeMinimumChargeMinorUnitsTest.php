<?php

declare(strict_types=1);

use App\Support\StripeMinimumChargeMinorUnits;

it('maps NOK to Stripe documented minimum minor units', function (): void {
    expect(StripeMinimumChargeMinorUnits::forCurrency('nok'))->toBe(300)
        ->and(StripeMinimumChargeMinorUnits::forCurrency('NOK'))->toBe(300);
});

it('describes two-decimal minimums clearly', function (): void {
    expect(StripeMinimumChargeMinorUnits::describeMinimumCharge('nok'))->toBe('3.00 NOK');
});

it('uses API minor amounts for zero-decimal currencies', function (): void {
    expect(StripeMinimumChargeMinorUnits::forCurrency('jpy'))->toBe(50)
        ->and(StripeMinimumChargeMinorUnits::describeMinimumCharge('jpy'))->toBe('50 JPY');
});

it('returns null for undocumented currencies so Stripe continues to enforce', function (): void {
    expect(StripeMinimumChargeMinorUnits::forCurrency('xxx'))
        ->toBeNull()
        ->and(StripeMinimumChargeMinorUnits::describeMinimumCharge('xxx'))
        ->toBeNull();
});
