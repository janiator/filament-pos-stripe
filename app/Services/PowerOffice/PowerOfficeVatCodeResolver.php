<?php

namespace App\Services\PowerOffice;

use App\Models\PowerOfficeIntegration;

class PowerOfficeVatCodeResolver
{
    /**
     * @var array<int, array<string, int>>
     */
    protected array $codeToIdByIntegration = [];

    public function __construct(
        protected PowerOfficeApiClient $apiClient,
    ) {}

    public function resolveZeroVatId(PowerOfficeIntegration $integration): int
    {
        $id = $this->resolveIdForCode($integration, '0');
        if ($id === null) {
            throw new \RuntimeException(
                'PowerOffice has no VAT code "0" (ingen mva). Required for voucher lines on accounts without a VAT code.'
            );
        }

        return $id;
    }

    public function resolveIdForCode(PowerOfficeIntegration $integration, string $code): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $integrationId = (int) $integration->getKey();
        if (! isset($this->codeToIdByIntegration[$integrationId])) {
            $this->codeToIdByIntegration[$integrationId] = $this->fetchCodeMap($integration);
        }

        return $this->codeToIdByIntegration[$integrationId][$code] ?? null;
    }

    /**
     * @return array<string, int>
     */
    protected function fetchCodeMap(PowerOfficeIntegration $integration): array
    {
        $response = $this->apiClient->get($integration, '/VatCodes');

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('vat_codes', $response);
            throw new \RuntimeException(
                'PowerOffice VatCodes request failed: HTTP '.$response->status()
                .$this->apiClient->summarizeErrorBody($response)
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('PowerOffice VatCodes returned invalid JSON.');
        }

        $map = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['Id'])) {
                continue;
            }
            $code = trim((string) ($row['Code'] ?? $row['VatCode'] ?? ''));
            if ($code === '') {
                continue;
            }
            $map[$code] = (int) $row['Id'];
        }

        return $map;
    }
}
