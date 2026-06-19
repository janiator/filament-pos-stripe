<?php

namespace App\Support\PowerOffice;

use App\Enums\PowerOfficeMappingBasis;

/**
 * Suggested PowerOffice ledger account numbers for new setups (Jobberiet / POS defaults).
 * Stored values on {@see \App\Models\PowerOfficeIntegration} always override these.
 */
final class PowerOfficeLedgerDefaults
{
    public static function mappingBasis(): PowerOfficeMappingBasis
    {
        return PowerOfficeMappingBasis::Category;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ledgerSettings(): array
    {
        return [
            'department_no' => '20',
            'commission_revenue_account_no' => '3023',
            'default_sales_account_no' => '3000',
            'payment_debits' => [
                'cash' => '1920',
                'card_present' => '1921',
                'card' => '1921',
                'vipps' => '1921',
                'mobile' => '1921',
            ],
            'payment_method_fees' => [
                'vipps' => [
                    'debit_account_no' => '7720',
                ],
            ],
            'payment_fee' => [
                'credit_account_no' => '2900',
                'debit_account_no' => '7900',
            ],
        ];
    }

    /**
     * Shared accounts copied onto each {@see \App\Models\PowerOfficeAccountMapping} row.
     *
     * @return array{vat_account_no: string, tips_account_no: string, cash_account_no: string, card_clearing_account_no: string, rounding_account_no: string}
     */
    public static function sharedMappingAccounts(): array
    {
        return [
            'vat_account_no' => '2700',
            'tips_account_no' => '3001',
            'cash_account_no' => '1920',
            'card_clearing_account_no' => '1921',
            'rounding_account_no' => '',
        ];
    }

    /**
     * @return array<string, string> basis_key => sales account
     */
    public static function vatRateSalesAccounts(): array
    {
        return [
            '0' => '5910',
            '15' => '3000',
            '25' => '3000',
        ];
    }

    /**
     * Match product collection / varegruppe name to a sales account (Neverstua, Vaskeri, …).
     */
    public static function salesAccountForCollectionName(string $name): ?string
    {
        return self::salesAccountForVaregruppeName($name);
    }

    public static function salesAccountForArticleGroupName(string $name): ?string
    {
        return self::salesAccountForVaregruppeName($name);
    }

    public static function salesAccountForVaregruppeName(string $name): ?string
    {
        $normalized = mb_strtolower(trim($name));

        foreach (self::collectionNameSalesAccounts() as $needle => $account) {
            if (str_contains($normalized, $needle)) {
                return $account;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> lowercase substring => account no
     */
    public static function collectionNameSalesAccounts(): array
    {
        return [
            'neverstua' => '3000',
            'vaskeri' => '3020',
            'uteavdeling' => '3022',
            'stuttreist' => '3023',
            'kantine' => '5910',
        ];
    }

    /**
     * @return array<string, string> Filament form keys => default values
     */
    public static function ledgerFormDefaults(): array
    {
        $l = self::ledgerSettings();
        $pd = is_array($l['payment_debits'] ?? null) ? $l['payment_debits'] : [];
        $pf = is_array($l['payment_fee'] ?? null) ? $l['payment_fee'] : [];
        $pmf = is_array($l['payment_method_fees'] ?? null) ? $l['payment_method_fees'] : [];
        $vippsFee = is_array($pmf['vipps'] ?? null) ? $pmf['vipps'] : [];
        $shared = self::sharedMappingAccounts();

        $vatSales = [];
        foreach (self::vatRateSalesAccounts() as $rate => $account) {
            $vatSales['vat_sales_'.$rate] = $account;
        }

        return array_merge($vatSales, [
            'ledger_department_no' => (string) ($l['department_no'] ?? ''),
            'ledger_commission_revenue_account_no' => (string) ($l['commission_revenue_account_no'] ?? ''),
            'ledger_default_sales_account_no' => (string) ($l['default_sales_account_no'] ?? ''),
            'ledger_payment_debit_cash' => (string) ($pd['cash'] ?? ''),
            'ledger_payment_debit_card_present' => (string) ($pd['card_present'] ?? ''),
            'ledger_payment_debit_card' => (string) ($pd['card'] ?? ''),
            'ledger_payment_debit_vipps' => (string) ($pd['vipps'] ?? ''),
            'ledger_payment_debit_mobile' => (string) ($pd['mobile'] ?? ''),
            'ledger_payment_debit_gift_token' => (string) ($pd['gift_token'] ?? ''),
            'ledger_payment_debit_default' => (string) ($pd['default'] ?? ''),
            'ledger_giftcard_liability_account_no' => '',
            'ledger_interim_liquid_account_no' => '',
            'ledger_fee_credit_account_no' => (string) ($pf['credit_account_no'] ?? ''),
            'ledger_fee_debit_account_no' => (string) ($pf['debit_account_no'] ?? ''),
            'ledger_vipps_fee_debit_account_no' => (string) ($vippsFee['debit_account_no'] ?? ''),
            'ledger_payout_credit_account_no' => '',
            'ledger_payout_debit_bank_account_no' => '',
            'ledger_shared_vat_account_no' => (string) $shared['vat_account_no'],
            'ledger_shared_tips_account_no' => (string) $shared['tips_account_no'],
            'ledger_shared_cash_account_no' => (string) $shared['cash_account_no'],
            'ledger_shared_card_clearing_account_no' => (string) $shared['card_clearing_account_no'],
            'ledger_shared_rounding_account_no' => (string) $shared['rounding_account_no'],
        ]);
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public static function mergeFormDefaults(array $values): array
    {
        $merged = self::ledgerFormDefaults();

        foreach ($values as $key => $value) {
            if (filled($value)) {
                $merged[$key] = (string) $value;
            }
        }

        return $merged;
    }
}
