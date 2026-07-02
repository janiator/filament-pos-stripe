<?php

namespace App\Support\PowerOffice;

use App\Enums\PowerOfficeMappingBasis;

/**
 * VAT rate keys used when splitting Z-report net sales by {@see \App\Enums\PowerOfficeMappingBasis::Vat}.
 * Keys must match {@see \App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder::bucketsForVat()} (stringified integer rates).
 */
final class PowerOfficeStandardVatRates
{
    /**
     * @return list<string>
     */
    public static function basisKeys(): array
    {
        $out = [];
        foreach (array_keys(self::options()) as $key) {
            $out[] = (string) $key;
        }

        return $out;
    }

    /**
     * @return array<string, string> basis_key => label
     */
    public static function options(): array
    {
        return [
            '0' => '0%',
            '15' => '15%',
            '25' => '25%',
        ];
    }

    /**
     * Canonical basis key for a numeric VAT rate string (e.g. JSON "15", 15, "015" → "15").
     * Returns null for keys that are not one of the standard rates (e.g. payment method slugs).
     */
    public static function canonicalVatBasisKey(string $key): ?string
    {
        $trimmed = trim($key);
        if ($trimmed === '') {
            return null;
        }
        if (! is_numeric($trimmed)) {
            return null;
        }
        $asInt = (string) (int) $trimmed;

        return in_array($asInt, self::basisKeys(), true) ? $asInt : null;
    }

    /**
     * @param  array<mixed, mixed>|null  $split
     * @return array<string, int> canonical basis key => net minor (øre), only 0 / 15 / 25
     */
    public static function normalizeSalesNetMinorByVatRateMap(?array $split): array
    {
        if (! is_array($split) || $split === []) {
            return [];
        }
        $out = [];
        foreach ($split as $key => $val) {
            $canonical = self::canonicalVatBasisKey((string) $key);
            if ($canonical === null) {
                continue;
            }
            $n = (int) $val;
            if ($n <= 0) {
                continue;
            }
            $out[$canonical] = ($out[$canonical] ?? 0) + $n;
        }

        return $out;
    }

    /**
     * @param  array<mixed, mixed>|null  $split
     * @return array<string, int> canonical basis key => output VAT minor (øre)
     */
    public static function normalizeVatMinorByVatRateMap(?array $split): array
    {
        if (! is_array($split) || $split === []) {
            return [];
        }
        $out = [];
        foreach ($split as $key => $val) {
            $canonical = self::canonicalVatBasisKey((string) $key);
            if ($canonical === null) {
                continue;
            }
            $n = (int) $val;
            if ($n <= 0) {
                continue;
            }
            $out[$canonical] = ($out[$canonical] ?? 0) + $n;
        }

        return $out;
    }

    /**
     * Gross (VAT-inclusive) sales credit buckets for PowerOffice manual vouchers.
     * PowerOffice derives VAT from each GL account's vat code, so posted amounts must be gross
     * and no explicit VAT line is booked (same as an accountant entering a manual voucher).
     *
     * For the VAT basis the Z-report stores net + VAT per rate; other bases already bucket gross amounts.
     *
     * @param  array<string, int>  $mappingBuckets
     * @return array<string, int>
     */
    public static function resolveGrossSalesCreditBucketsForLedger(
        PowerOfficeMappingBasis $basis,
        array $zReport,
        array $mappingBuckets,
    ): array {
        if ($basis !== PowerOfficeMappingBasis::Vat) {
            return $mappingBuckets;
        }

        $netSplit = self::normalizeSalesNetMinorByVatRateMap($zReport['sales_net_minor_by_vat_rate'] ?? null);
        if ($netSplit !== []) {
            $vatSplit = self::normalizeVatMinorByVatRateMap($zReport['vat_minor_by_vat_rate'] ?? null);
            $gross = [];
            foreach ($netSplit as $rate => $net) {
                $gross[$rate] = $net + ($vatSplit[$rate] ?? 0);
            }

            return $gross;
        }

        $grossTotal = (int) ($zReport['net_amount'] ?? 0) + (int) ($zReport['vat_amount'] ?? 0);
        if ($grossTotal <= 0) {
            return [];
        }

        $rate = (string) (int) ($zReport['vat_rate'] ?? 25);

        return [$rate => $grossTotal];
    }

    /**
     * Sales credit buckets for Tripletex / PowerOffice Z-report payloads: prefer normalized VAT split,
     * else the mapping buckets, with a single-rate VAT fallback when mapping buckets are empty.
     *
     * @param  array<string, int>  $mappingBuckets
     * @return array<string, int>
     */
    public static function resolveSalesCreditBucketsForLedger(
        PowerOfficeMappingBasis $basis,
        array $zReport,
        array $mappingBuckets,
    ): array {
        $fromSplit = self::normalizeSalesNetMinorByVatRateMap($zReport['sales_net_minor_by_vat_rate'] ?? null);
        if ($fromSplit !== []) {
            return $fromSplit;
        }

        $bucketTotal = array_sum($mappingBuckets);
        $netAmount = (int) ($zReport['net_amount'] ?? 0);
        if ($bucketTotal <= 0 && $netAmount > 0 && $basis === PowerOfficeMappingBasis::Vat) {
            $rate = (string) (int) ($zReport['vat_rate'] ?? 25);

            return [$rate => $netAmount];
        }

        return $mappingBuckets;
    }
}
