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
            throw new PowerOfficeUnresolvedGlAccountsException($missing);
        }

        return $map;
    }
}
