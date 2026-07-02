<?php

namespace App\Services\PowerOffice;

use App\Exceptions\PowerOffice\PowerOfficeUnresolvedGlAccountsException;
use App\Models\PowerOfficeIntegration;

class PowerOfficeGeneralLedgerAccountResolver
{
    public function __construct(
        protected PowerOfficeApiClient $apiClient,
    ) {}

    /**
     * Resolve PowerOffice Go GL account IDs (and optional VAT code ids) for the given account numbers.
     *
     * @param  list<string>  $accountNos
     * @return array<string, array{id: int, vat_code_id: ?int}>
     */
    public function resolveMapForAccountNos(PowerOfficeIntegration $integration, array $accountNos): array
    {
        $normalized = [];
        foreach ($accountNos as $no) {
            $s = trim((string) $no);
            if ($s !== '') {
                $normalized[$s] = true;
            }
        }
        $unique = array_keys($normalized);
        if ($unique === []) {
            return [];
        }

        $response = $this->apiClient->get($integration, '/GeneralLedgerAccounts', [
            'accountNos' => implode(',', $unique),
        ]);

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('general_ledger_accounts', $response);
            throw new \RuntimeException(
                'PowerOffice GeneralLedgerAccounts request failed: HTTP '.$response->status()
                .$this->apiClient->summarizeErrorBody($response)
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('PowerOffice GeneralLedgerAccounts returned invalid JSON.');
        }

        $map = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['Id'], $row['AccountNo'])) {
                continue;
            }
            $key = trim((string) $row['AccountNo']);
            $map[$key] = [
                'id' => (int) $row['Id'],
                'vat_code_id' => isset($row['VatCodeId']) && $row['VatCodeId'] !== null
                    ? (int) $row['VatCodeId']
                    : null,
            ];
        }

        $missing = [];
        foreach ($unique as $code) {
            if (! isset($map[$code])) {
                $missing[] = $code;
            }
        }

        if ($missing !== []) {
            $map = array_replace($map, $this->resolveSupplierSubledgerMap($integration, $missing));
            $missing = [];
            foreach ($unique as $code) {
                if (! isset($map[$code])) {
                    $missing[] = $code;
                }
            }
        }

        if ($missing !== []) {
            throw new PowerOfficeUnresolvedGlAccountsException($missing);
        }

        return $this->enrichMissingVatCodeIds($integration, $map);
    }

    /**
     * Resolve PowerOffice supplier numbers to their sub-ledger account IDs.
     *
     * @param  list<string>  $accountNos
     * @return array<string, array{id: int, vat_code_id: ?int}>
     */
    protected function resolveSupplierSubledgerMap(PowerOfficeIntegration $integration, array $accountNos): array
    {
        $supplierNos = array_values(array_filter($accountNos, fn (string $accountNo): bool => is_numeric($accountNo)));
        if ($supplierNos === []) {
            return [];
        }

        $response = $this->apiClient->get($integration, '/Suppliers', [
            'supplierNos' => implode(',', $supplierNos),
            'Fields' => 'Number,SubledgerAccountId',
        ]);

        if ($response->status() === 204) {
            return [];
        }

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('suppliers_subledger_accounts', $response);
            throw new \RuntimeException(
                'PowerOffice Suppliers request failed: HTTP '.$response->status()
                .$this->apiClient->summarizeErrorBody($response)
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('PowerOffice Suppliers returned invalid JSON.');
        }

        $map = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['Number'], $row['SubledgerAccountId'])) {
                continue;
            }
            $subledgerAccountId = $row['SubledgerAccountId'];
            if (! is_numeric($subledgerAccountId)) {
                continue;
            }
            $map[trim((string) $row['Number'])] = [
                'id' => (int) $subledgerAccountId,
                'vat_code_id' => null,
            ];
        }

        return $map;
    }

    /**
     * Supplier sub-ledger accounts are resolved by id via /Suppliers; their VatCodeId is only
     * available from GET /GeneralLedgerAccounts/{id}. Locked accounts require VatId on voucher lines.
     *
     * @param  array<string, array{id: int, vat_code_id: ?int}>  $map
     * @return array<string, array{id: int, vat_code_id: ?int}>
     */
    protected function enrichMissingVatCodeIds(PowerOfficeIntegration $integration, array $map): array
    {
        foreach ($map as $code => $entry) {
            if ($entry['vat_code_id'] !== null) {
                continue;
            }

            $response = $this->apiClient->get($integration, '/GeneralLedgerAccounts/'.$entry['id'], [
                'Fields' => 'Id,VatCodeId',
            ]);

            if (! $response->successful()) {
                $this->apiClient->logFailedResponse('general_ledger_account_by_id', $response);
                throw new \RuntimeException(
                    'PowerOffice GeneralLedgerAccounts/{id} request failed: HTTP '.$response->status()
                    .$this->apiClient->summarizeErrorBody($response)
                );
            }

            $row = $response->json();
            if (! is_array($row)) {
                throw new \RuntimeException('PowerOffice GeneralLedgerAccounts/{id} returned invalid JSON.');
            }

            if (isset($row['VatCodeId']) && $row['VatCodeId'] !== null) {
                $map[$code]['vat_code_id'] = (int) $row['VatCodeId'];
            }
        }

        return $map;
    }
}
