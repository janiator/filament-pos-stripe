<?php

namespace App\Services\PowerOffice;

use App\Models\PowerOfficeIntegration;

class PowerOfficeDepartmentResolver
{
    public function __construct(
        protected PowerOfficeApiClient $apiClient,
    ) {}

    public function resolveIdForDepartmentNo(PowerOfficeIntegration $integration, string $departmentNo): ?int
    {
        $departmentNo = trim($departmentNo);
        if ($departmentNo === '') {
            return null;
        }

        $response = $this->apiClient->get($integration, '/Departments');

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('departments', $response);

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['Id'])) {
                continue;
            }
            $code = trim((string) ($row['Code'] ?? $row['DepartmentNo'] ?? $row['Number'] ?? ''));
            if ($code === $departmentNo) {
                return (int) $row['Id'];
            }
        }

        return null;
    }
}
