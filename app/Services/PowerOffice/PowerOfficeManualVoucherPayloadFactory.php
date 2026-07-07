<?php

namespace App\Services\PowerOffice;

use App\Enums\PowerOfficeVoucherPostingMode;

/**
 * Maps our internal Z-report ledger payload to PowerOffice Go v2 manual journal POST bodies.
 *
 * @see https://developer.poweroffice.net/workflows/endpoints/voucher-workflows/voucher-posting Direct posting
 * @see https://developer.poweroffice.net/endpoints/voucher-workflows/journalentryvoucher Journal-entry drafts
 */
class PowerOfficeManualVoucherPayloadFactory
{
    /**
     * @param  array<string, mixed>  $ledgerPayload  Output of {@see PowerOfficeLedgerPayloadBuilder::build()}
     * @param  array<string, array{id: int, vat_code_id: ?int}>  $accountMap
     * @return array<string, mixed>
     */
    public function build(
        array $ledgerPayload,
        array $accountMap,
        string $externalImportReference,
        int $defaultVatId,
        PowerOfficeVoucherPostingMode $postingMode = PowerOfficeVoucherPostingMode::Direct,
    ): array {
        return match ($postingMode) {
            PowerOfficeVoucherPostingMode::Direct => $this->buildDirectPostingPayload(
                $ledgerPayload,
                $accountMap,
                $externalImportReference,
                $defaultVatId,
            ),
            PowerOfficeVoucherPostingMode::JournalEntry => $this->buildJournalEntryPayload(
                $ledgerPayload,
                $accountMap,
                $externalImportReference,
                $defaultVatId,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $ledgerPayload
     * @param  array<string, array{id: int, vat_code_id: ?int}>  $accountMap
     * @return array<string, mixed>
     */
    protected function buildDirectPostingPayload(
        array $ledgerPayload,
        array $accountMap,
        string $externalImportReference,
        int $defaultVatId,
    ): array {
        $currency = strtoupper((string) ($ledgerPayload['currency'] ?? 'NOK'));
        $documentDate = (string) ($ledgerPayload['document_date'] ?? now()->format('Y-m-d'));
        $voucherDateIso = $documentDate.'T12:00:00Z';
        $departmentId = $this->resolvedDepartmentId($ledgerPayload);

        $lines = [];
        foreach ($this->normalizedLedgerLines($ledgerPayload, $accountMap) as $normalized) {
            $amount = $normalized['debit_minor'] > 0
                ? round($normalized['debit_minor'] / 100, 2)
                : -1 * round($normalized['credit_minor'] / 100, 2);

            if (abs($amount) < 0.0001) {
                continue;
            }

            $row = [
                'PostingDate' => $voucherDateIso,
                'AccountId' => $normalized['account_id'],
                'CurrencyAmount' => $amount,
                'CurrencyCode' => $currency,
                'Description' => $normalized['description'],
                'VatId' => $normalized['vat_id'] ?? $defaultVatId,
            ];

            if ($departmentId !== null) {
                $row['DepartmentId'] = $departmentId;
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

    /**
     * @param  array<string, mixed>  $ledgerPayload
     * @param  array<string, array{id: int, vat_code_id: ?int}>  $accountMap
     * @return array<string, mixed>
     */
    protected function buildJournalEntryPayload(
        array $ledgerPayload,
        array $accountMap,
        string $externalImportReference,
        int $defaultVatId,
    ): array {
        $currency = strtoupper((string) ($ledgerPayload['currency'] ?? 'NOK'));
        $documentDate = (string) ($ledgerPayload['document_date'] ?? now()->format('Y-m-d'));
        $departmentId = $this->resolvedDepartmentId($ledgerPayload);

        $lines = [];
        foreach ($this->normalizedLedgerLines($ledgerPayload, $accountMap) as $normalized) {
            $amount = round(max($normalized['debit_minor'], $normalized['credit_minor']) / 100, 2);
            if ($amount < 0.0001) {
                continue;
            }

            $vatId = $normalized['vat_id'] ?? $defaultVatId;
            $row = [
                'PostingDate' => $documentDate,
                'CurrencyAmount' => $amount,
                'CurrencyCode' => $currency,
                'Description' => $normalized['description'],
            ];

            if ($normalized['debit_minor'] > 0) {
                $row['DebitAccountId'] = $normalized['account_id'];
                $row['DebitVatId'] = $vatId;
            } else {
                $row['CreditAccountId'] = $normalized['account_id'];
                $row['CreditVatId'] = $vatId;
            }

            if ($departmentId !== null) {
                $row['DepartmentId'] = $departmentId;
            }

            $lines[] = $row;
        }

        if ($lines === []) {
            throw new \InvalidArgumentException('PowerOffice manual voucher has no lines to post.');
        }

        $payload = [
            'VoucherDate' => $documentDate,
            'CurrencyCode' => $currency,
            'Description' => (string) ($ledgerPayload['description'] ?? ''),
            'Comment' => 'POS sync ref: '.$externalImportReference,
            'ManualVoucherLines' => $lines,
        ];

        if ($departmentId !== null) {
            $payload['DepartmentId'] = $departmentId;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $ledgerPayload
     * @return list<array{account_id: int, debit_minor: int, credit_minor: int, description: string, vat_id: ?int}>
     */
    protected function normalizedLedgerLines(array $ledgerPayload, array $accountMap): array
    {
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

            $lines[] = [
                'account_id' => (int) $resolved['id'],
                'debit_minor' => $debitMinor,
                'credit_minor' => $creditMinor,
                'description' => (string) ($line['description'] ?? ''),
                'vat_id' => isset($resolved['vat_code_id']) ? (int) $resolved['vat_code_id'] : null,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $ledgerPayload
     */
    protected function resolvedDepartmentId(array $ledgerPayload): ?int
    {
        $departmentId = $ledgerPayload['department_id'] ?? null;

        return is_numeric($departmentId) && (int) $departmentId > 0 ? (int) $departmentId : null;
    }
}
