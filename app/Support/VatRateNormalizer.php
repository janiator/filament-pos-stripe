<?php

namespace App\Support;

final class VatRateNormalizer
{
    /**
     * Normalize a VAT rate to decimal form (0–1), e.g. 15 → 0.15, 0.15 → 0.15.
     */
    public static function toDecimal(?float $rate, float $default = 0.25): float
    {
        if ($rate === null) {
            return $default;
        }

        if ($rate <= 0) {
            return 0.0;
        }

        if ($rate > 1) {
            return $rate / 100;
        }

        return $rate;
    }

    /**
     * Display percent (0–100) from decimal rate.
     */
    public static function toDisplayPercent(float $decimalRate): int
    {
        return (int) round(self::toDecimal($decimalRate) * 100);
    }

    /**
     * Extract tax (øre) from a tax-inclusive amount.
     */
    public static function extractTaxOreFromInclusiveAmount(int $amountOre, float $decimalRate): int
    {
        $rate = self::toDecimal($decimalRate);

        if ($rate <= 0 || $amountOre <= 0) {
            return 0;
        }

        return (int) round($amountOre * ($rate / (1 + $rate)));
    }
}
