<?php

namespace App\Services\PowerOffice;

/**
 * Maps our internal Z-report ledger payload to PowerOffice Go v2
 * {@see https://developer.poweroffice.net/workflows/endpoints/voucher-workflows/voucher-posting Manual journal} POST body.
 */
class PowerOfficeManualVoucherPayloadFactory
{
    /**
     * @param  array<string, mixed>  $ledgerPayload  Output of {@see PowerOfficeLedgerPayloadBuilder::build()}
     * @param  array<string, array{id: int, vat_code_id: ?int}>  $accountMap
     * @return array<string, mixed>
     */
    public function build(array $ledgerPayload, array $accountMap, string $externalImportReference): array
    {
        $currency = strtoupper((string) ($ledgerPayload['currency'] ?? 'NOK'));
        $documentDate = (string) ($ledgerPayload['document_date'] ?? now()->format('Y-m-d'));
        $voucherDateIso = $documentDate.'T12:00:00Z';

        $lines = [];
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
                throw new \InvalidArgumentException(
                    'PowerOffice manual voucher line cannot have both debit and credit: '.$accountCode
                );
            }

            $resolved = $accountMap[$accountCode] ?? null;
            if (! $resolved) {
                throw new \InvalidArgumentException('Missing resolved GL account for code: '.$accountCode);
            }

            $amount = $debitMinor > 0
                ? round($debitMinor / 100, 2)
                : -1 * round($creditMinor / 100, 2);

            if (abs($amount) < 0.0001) {
                continue;
            }

            $row = [
                'PostingDate' => $voucherDateIso,
                'AccountId' => $resolved['id'],
                'CurrencyAmount' => $amount,
                'CurrencyCode' => $currency,
                'Description' => (string) ($line['description'] ?? ''),
            ];

            if ($resolved['vat_code_id'] !== null) {
                $row['VatId'] = $resolved['vat_code_id'];
            }

            $lines[] = $row;
        }

        if ($lines === []) {
            throw new \InvalidArgumentException('PowerOffice manual voucher has no lines to post.');
        }

        return [
            'VoucherDate' => $voucherDateIso,
            'CurrencyCode' => $currency,
            'Description' => (string) ($ledgerPayload['description'] ?? ''),
            'ExternalImportReference' => $externalImportReference,
            'VoucherLines' => $lines,
        ];
    }
}
