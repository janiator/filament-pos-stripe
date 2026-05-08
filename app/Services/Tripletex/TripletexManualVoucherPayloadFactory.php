<?php

namespace App\Services\Tripletex;

/**
 * Maps internal ledger lines (minor units, debit/credit) to Tripletex voucher JSON.
 *
 * @see https://tripletex.no/v2/docs/ — ledger voucher
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
                ? round($debitMinor / 100, 2)
                : -1 * round($creditMinor / 100, 2);

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

            $vatId = $line['tripletex_vat_type_id'] ?? null;
            if (is_numeric($vatId) && (int) $vatId > 0) {
                $posting['vatType'] = ['id' => (int) $vatId];
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
}
