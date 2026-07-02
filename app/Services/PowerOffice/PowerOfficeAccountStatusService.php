<?php

namespace App\Services\PowerOffice;

use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Vendor;
use App\Support\PowerOffice\PowerOfficeLedgerSettings;

/**
 * Checks which configured account numbers exist in PowerOffice Go, and creates missing ones.
 * GL accounts use GET/POST /GeneralLedgerAccounts; vendor reskontro numbers are supplier
 * sub-ledger accounts and use GET/POST /Suppliers.
 */
class PowerOfficeAccountStatusService
{
    public function __construct(
        protected PowerOfficeApiClient $apiClient,
    ) {}

    /**
     * @return array{
     *     gl: list<array{account_no: string, purposes: list<string>, is_sales: bool, exists: bool, suggested_vat_code: string}>,
     *     suppliers: list<array{number: string, vendor: string, exists: bool}>,
     * }
     */
    public function check(PowerOfficeIntegration $integration): array
    {
        $glAccounts = $this->usedGlAccounts($integration);
        $existingGl = $this->existingGlAccountNos($integration, array_keys($glAccounts));

        $gl = [];
        foreach ($glAccounts as $accountNo => $meta) {
            $gl[] = [
                'account_no' => (string) $accountNo,
                'purposes' => $meta['purposes'],
                'is_sales' => $meta['is_sales'],
                'exists' => in_array((string) $accountNo, $existingGl, true),
                'suggested_vat_code' => $meta['is_sales'] && ! $meta['zero_vat'] ? '3' : '0',
            ];
        }
        usort($gl, fn (array $a, array $b): int => strcmp($a['account_no'], $b['account_no']));

        $supplierNumbers = $this->usedSupplierNumbers($integration);
        $existingSuppliers = $this->existingSupplierNos($integration, array_keys($supplierNumbers));

        $suppliers = [];
        foreach ($supplierNumbers as $number => $vendorName) {
            $suppliers[] = [
                'number' => (string) $number,
                'vendor' => $vendorName,
                'exists' => in_array((string) $number, $existingSuppliers, true),
            ];
        }
        usort($suppliers, fn (array $a, array $b): int => strcmp($a['number'], $b['number']));

        return ['gl' => $gl, 'suppliers' => $suppliers];
    }

    /**
     * Every GL account number referenced by mappings, ledger settings, and vendor commission accounts.
     *
     * @return array<string, array{purposes: list<string>, is_sales: bool, zero_vat: bool}>
     */
    public function usedGlAccounts(PowerOfficeIntegration $integration): array
    {
        $accounts = [];
        $add = function (?string $accountNo, string $purpose, bool $isSales = false, bool $zeroVat = false) use (&$accounts): void {
            $no = trim((string) $accountNo);
            if ($no === '') {
                return;
            }
            $accounts[$no] ??= ['purposes' => [], 'is_sales' => false, 'zero_vat' => false];
            if (! in_array($purpose, $accounts[$no]['purposes'], true)) {
                $accounts[$no]['purposes'][] = $purpose;
            }
            $accounts[$no]['is_sales'] = $accounts[$no]['is_sales'] || $isSales;
            $accounts[$no]['zero_vat'] = $accounts[$no]['zero_vat'] || $zeroVat;
        };

        $integration->loadMissing('accountMappings');
        foreach ($integration->accountMappings as $mapping) {
            /** @var PowerOfficeAccountMapping $mapping */
            $label = $mapping->basis_label ?? $mapping->basis_key;
            $add($mapping->sales_account_no, __('Sales — :line', ['line' => $label]), true, $mapping->basis_key === '0');
            $add($mapping->vat_account_no, __('Output VAT'));
            $add($mapping->tips_account_no, __('Tips'));
            $add($mapping->cash_account_no, __('Cash (fallback)'));
            $add($mapping->card_clearing_account_no, __('Card / clearing (fallback)'));
            $add($mapping->rounding_account_no, __('Rounding'));
            $add($mapping->fees_account_no, __('Fees'));
        }

        $ledger = PowerOfficeLedgerSettings::ledger($integration);
        $add($ledger['default_sales_account_no'] ?? null, __('Default sales (fallback)'), true);
        $add($ledger['commission_revenue_account_no'] ?? null, __('Commission revenue'), true);
        $add($ledger['giftcard_liability_account_no'] ?? null, __('Gift card liability'));
        $add($ledger['interim_liquid_account_no'] ?? null, __('Interim / PSP liquid'));

        foreach (is_array($ledger['payment_debits'] ?? null) ? $ledger['payment_debits'] : [] as $method => $accountNo) {
            $add(is_string($accountNo) ? $accountNo : null, __('Payment debit — :method', ['method' => $method]));
        }

        $fee = is_array($ledger['payment_fee'] ?? null) ? $ledger['payment_fee'] : [];
        $add($fee['credit_account_no'] ?? null, __('Fee settlement (credit)'));
        $add($fee['debit_account_no'] ?? null, __('Fee expense (debit)'));

        $vippsFee = data_get($ledger, 'payment_method_fees.vipps.debit_account_no');
        $add(is_string($vippsFee) ? $vippsFee : null, __('Vipps fee expense'));

        $payout = is_array($ledger['payout'] ?? null) ? $ledger['payout'] : [];
        $add($payout['credit_account_no'] ?? null, __('Payout settlement (credit)'));
        $add($payout['debit_bank_account_no'] ?? null, __('Bank (payout debit)'));

        foreach ($this->storeVendors($integration) as $vendor) {
            $add($vendor->commission_revenue_account_number, __('Commission — :vendor', ['vendor' => $vendor->name]), true);
        }

        return $accounts;
    }

    /**
     * Vendor reskontro (supplier sub-ledger) numbers, keyed by number => vendor name.
     *
     * @return array<string, string>
     */
    public function usedSupplierNumbers(PowerOfficeIntegration $integration): array
    {
        $numbers = [];
        foreach ($this->storeVendors($integration) as $vendor) {
            $no = trim((string) $vendor->supplier_ledger_account_number);
            if ($no !== '' && is_numeric($no)) {
                $numbers[$no] = $vendor->name;
            }
        }

        return $numbers;
    }

    /**
     * @param  list<string>  $accountNos
     * @return list<string> account numbers that exist in PowerOffice
     */
    public function existingGlAccountNos(PowerOfficeIntegration $integration, array $accountNos): array
    {
        if ($accountNos === []) {
            return [];
        }

        $response = $this->apiClient->get($integration, '/GeneralLedgerAccounts', [
            'accountNos' => implode(',', $accountNos),
        ]);

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('general_ledger_accounts_check', $response);
            throw new \RuntimeException(
                'PowerOffice GeneralLedgerAccounts request failed: HTTP '.$response->status()
                .$this->apiClient->summarizeErrorBody($response)
            );
        }

        $existing = [];
        foreach (is_array($response->json()) ? $response->json() : [] as $row) {
            if (is_array($row) && isset($row['AccountNo'])) {
                $existing[] = trim((string) $row['AccountNo']);
            }
        }

        return $existing;
    }

    /**
     * @param  list<string>  $supplierNos
     * @return list<string> supplier numbers that exist in PowerOffice
     */
    public function existingSupplierNos(PowerOfficeIntegration $integration, array $supplierNos): array
    {
        if ($supplierNos === []) {
            return [];
        }

        $response = $this->apiClient->get($integration, '/Suppliers', [
            'supplierNos' => implode(', ', $supplierNos),
            'Fields' => 'Number',
        ]);

        // 204 means no suppliers matched the filter.
        if ($response->status() === 204) {
            return [];
        }

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('suppliers_check', $response);
            throw new \RuntimeException(
                'PowerOffice Suppliers request failed: HTTP '.$response->status()
                .$this->apiClient->summarizeErrorBody($response)
            );
        }

        $existing = [];
        foreach (is_array($response->json()) ? $response->json() : [] as $row) {
            if (is_array($row) && isset($row['Number'])) {
                $existing[] = trim((string) $row['Number']);
            }
        }

        return $existing;
    }

    /**
     * Create a GL account in PowerOffice Go. VatCode is required by the API (e.g. "3" for 25% output VAT, "0" for none).
     *
     * @return array{ok: bool, error: ?string}
     */
    public function createGlAccount(PowerOfficeIntegration $integration, string $accountNo, string $name, string $vatCode): array
    {
        $response = $this->apiClient->post($integration, '/GeneralLedgerAccounts', [
            'AccountNo' => (int) $accountNo,
            'Name' => $name,
            'IsActive' => true,
            'VatCode' => $vatCode,
        ]);

        if ($response->successful()) {
            return ['ok' => true, 'error' => null];
        }

        $this->apiClient->logFailedResponse('general_ledger_account_create', $response);

        return [
            'ok' => false,
            'error' => 'HTTP '.$response->status().$this->apiClient->summarizeErrorBody($response),
        ];
    }

    /**
     * Create a supplier with a fixed reskontro number in PowerOffice Go.
     * The number must be within the client's supplier sub-ledger number series.
     *
     * @return array{ok: bool, error: ?string}
     */
    public function createSupplier(PowerOfficeIntegration $integration, string $number, string $name): array
    {
        $response = $this->apiClient->post($integration, '/Suppliers', [
            'Number' => (int) $number,
            'Name' => $name,
        ]);

        if ($response->successful()) {
            return ['ok' => true, 'error' => null];
        }

        $this->apiClient->logFailedResponse('supplier_create', $response);

        return [
            'ok' => false,
            'error' => 'HTTP '.$response->status().$this->apiClient->summarizeErrorBody($response),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Vendor>
     */
    protected function storeVendors(PowerOfficeIntegration $integration): \Illuminate\Database\Eloquent\Collection
    {
        return Vendor::query()
            ->where('store_id', $integration->store_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }
}
