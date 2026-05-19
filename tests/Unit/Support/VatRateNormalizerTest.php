<?php

use App\Support\VatRateNormalizer;

it('normalizes negative rates to zero', function () {
    expect(VatRateNormalizer::toDecimal(-0.15))->toBe(0.0)
        ->and(VatRateNormalizer::toDecimal(-15.0))->toBe(0.0);
});

it('normalizes percent and decimal rates consistently', function () {
    expect(VatRateNormalizer::toDecimal(25.0))->toBe(0.25)
        ->and(VatRateNormalizer::toDecimal(0.25))->toBe(0.25)
        ->and(VatRateNormalizer::toDecimal(0.0))->toBe(0.0)
        ->and(VatRateNormalizer::toDecimal(null))->toBe(0.25);
});
