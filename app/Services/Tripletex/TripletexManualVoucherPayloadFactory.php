<?php

namespace App\Services\Tripletex;

/**
 * Maps internal ledger lines (minor units, debit/credit) to Tripletex voucher JSON.
 *
 * Amounts: {@see $ledgerPayload} lines use **integer minor units** (e.g. NOK øre). Each posting
 * uses `amountGross` / `amountGrossCurrency` as a major-unit float with two decimals, **debit
 * positive and credit negative**, matching the legacy Merano-Tripletex-Sync `voucherBuilder.js`
 * (`signedAmt = l.credit ? -amt : amt` after `Math.abs(parseFloat(l.amount.toFixed(2)))`).
 * Integer minors avoid float drift from the old script’s summed float `amount` field.
 *
 * @see https://tripletex.no/v2/docs/ — ledger voucher
 *
 * VAT on postings: if a line sets `tripletex_vat_type_id` (numeric, including 0), that value
 * is sent as `vatType.id`. Otherwise, when Tripletex’s resolved ledger account includes
 * `vatType.id` (from GET /ledger/account), that id is copied to the posting so locked
 * accounts (e.g. kiosk revenue tied to one VAT code) validate without Filament overrides.
 */
class TripletexManualVoucherPayloadFactory
{
    /**
     * @param  array<string, mixed>  $ledgerPayload  Same shape as {@see \App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder::build()} output
     * @param  array<string, array<string, mixed>>  $accountMap  Account number => Tripletex account object from API
     * @return array<string, mixed>
     */
    public function build(array $ledgerPayload, array $accountMap): array
    {
        $currency = strtoupper((string) ($ledgerPayload['currency'] ?? 'NOK'));
        $documentDate = (string) ($ledgerPayload['document_date'] ?? now()->format('Y-m-d'));
        $description = (string) ($ledgerPayload['description'] ?? 'POS voucher');

        $postings = [];
        $row = 0;
        $hasExplicitPostingDate = false;

        foreach ($ledgerPayload['lines'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }
            $accountCode = trim((string) ($line['account'] ?? ''));
            $debitMinor = (int) ($line['debit_minor'] ?? 0);
            $creditMinor = (int) ($line['credit_minor'] ?? 0);
            if ($accountCode === '' || ($debitMinor <= 0 && $creditMinor <= 0)) {
                continue;
            }
            if ($debitMinor > 0 && $creditMinor > 0) {
                throw new \InvalidArgumentException('Tripletex voucher line cannot have both debit and credit: '.$accountCode);
            }

            $resolved = $accountMap[$accountCode] ?? null;
            if (! is_array($resolved)) {
                throw new \InvalidArgumentException('Missing resolved ledger account for code: '.$accountCode);
            }

            $amountMajor = $debitMinor > 0
                ? self::minorUnitsToMajorFloat($debitMinor)
                : -1 * self::minorUnitsToMajorFloat($creditMinor);

            if (abs($amountMajor) < 0.0001) {
                continue;
            }

            $postingDateLine = null;
            if (filled($line['posting_date'] ?? null)) {
                $postingDateLine = (string) $line['posting_date'];
                $hasExplicitPostingDate = true;
            }

            $effectiveDate = $postingDateLine ?? $documentDate;

            $row++;
            $posting = [
                'row' => $row,
                'description' => (string) ($line['description'] ?? ''),
                'account' => $resolved,
                'amountGross' => $amountMajor,
                'amountGrossCurrency' => $amountMajor,
                'date' => $effectiveDate,
            ];

            $vatTypeId = self::postingVatTypeId($line, $resolved);
            if ($vatTypeId !== null) {
                $posting['vatType'] = ['id' => $vatTypeId];
            }

            $supplierId = $line['tripletex_supplier_id'] ?? null;
            if (is_numeric($supplierId) && (int) $supplierId > 0) {
                $posting['supplier'] = ['id' => (int) $supplierId];
            }

            $postings[] = $posting;
        }

        if ($postings === []) {
            throw new \InvalidArgumentException('Tripletex voucher has no postings.');
        }

        $headerDate = $documentDate;
        if ($hasExplicitPostingDate) {
            $dates = array_map(
                static fn (array $p): string => (string) ($p['date'] ?? $documentDate),
                $postings,
            );
            sort($dates, SORT_STRING);
            $headerDate = $dates[0] ?? $documentDate;
        }

        return [
            'date' => $headerDate,
            'description' => $description,
            'voucherType' => ['id' => 0],
            'postings' => $postings,
        ];
    }

    /**
     * Convert non-negative integer minor units to a major-currency float with two decimals.
     * Uses {@see round()} to two places so results align with Tripletex expectations and the
     * legacy script’s `toFixed(2)` output for representable cent values.
     */
    private static function minorUnitsToMajorFloat(int $minor): float
    {
        return round($minor / 100, 2);
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $resolvedAccount  Tripletex ledger account JSON
     */
    private static function postingVatTypeId(array $line, array $resolvedAccount): ?int
    {
        $explicit = $line['tripletex_vat_type_id'] ?? null;
        if (is_numeric($explicit)) {
            return (int) $explicit;
        }

        $fromAccount = data_get($resolvedAccount, 'vatType.id');
        if (is_numeric($fromAccount)) {
            return (int) $fromAccount;
        }

        return null;
    }
}
