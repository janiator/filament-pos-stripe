<?php

namespace App\Support\PowerOffice;

use App\Models\PowerOfficeIntegration;

/**
 * Optional ledger routing stored on {@see PowerOfficeIntegration::$settings} under the `ledger` key.
 * Mirrors patterns from PSP settlement scripts (interim/liquid, per payment type debits, fee/payout pairs).
 */
final class PowerOfficeLedgerSettings
{
    /** Stored mapping row holding shared VAT/tips/payment accounts when basis is vendor. */
    public const SHARED_MAPPING_BASIS_KEY = '_shared';

    /**
     * @return array<string, mixed>
     */
    public static function ledger(PowerOfficeIntegration $integration): array
    {
        $settings = $integration->settings;

        return is_array($settings) && isset($settings['ledger']) && is_array($settings['ledger'])
            ? $settings['ledger']
            : [];
    }

    public static function defaultSalesAccount(PowerOfficeIntegration $integration): ?string
    {
        $v = self::ledger($integration)['default_sales_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    public static function departmentNo(PowerOfficeIntegration $integration): ?string
    {
        $v = self::ledger($integration)['department_no'] ?? null;

        return filled($v) ? trim((string) $v) : null;
    }

    public static function commissionRevenueAccount(PowerOfficeIntegration $integration): ?string
    {
        $v = self::ledger($integration)['commission_revenue_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    public static function paymentDebitAccount(PowerOfficeIntegration $integration, string $method): ?string
    {
        $map = self::ledger($integration)['payment_debits'] ?? [];
        if (! is_array($map)) {
            return null;
        }

        $m = strtolower(trim($method));
        if (filled($map[$m] ?? null)) {
            return (string) $map[$m];
        }

        if (filled($map['default'] ?? null)) {
            return (string) $map['default'];
        }

        return null;
    }

    public static function paymentMethodFeeDebitAccount(PowerOfficeIntegration $integration, string $method): ?string
    {
        $map = self::ledger($integration)['payment_method_fees'] ?? [];
        if (! is_array($map)) {
            return null;
        }

        $m = strtolower(trim($method));
        $block = $map[$m] ?? null;
        if (! is_array($block)) {
            return null;
        }

        $v = $block['debit_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    /**
     * @return array{credit: ?string, debit: ?string}
     */
    public static function paymentFeeAccounts(PowerOfficeIntegration $integration): array
    {
        $block = self::ledger($integration)['payment_fee'] ?? [];

        return [
            'credit' => filled($block['credit_account_no'] ?? null) ? (string) $block['credit_account_no'] : null,
            'debit' => filled($block['debit_account_no'] ?? null) ? (string) $block['debit_account_no'] : null,
        ];
    }

    /**
     * @return array{credit: ?string, debit: ?string}
     */
    public static function payoutAccounts(PowerOfficeIntegration $integration): array
    {
        $block = self::ledger($integration)['payout'] ?? [];

        return [
            'credit' => filled($block['credit_account_no'] ?? null) ? (string) $block['credit_account_no'] : null,
            'debit' => filled($block['debit_bank_account_no'] ?? null) ? (string) $block['debit_bank_account_no'] : null,
        ];
    }

    public static function giftcardLiabilityAccount(PowerOfficeIntegration $integration): ?string
    {
        $v = self::ledger($integration)['giftcard_liability_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    public static function interimLiquidAccount(PowerOfficeIntegration $integration): ?string
    {
        $v = self::ledger($integration)['interim_liquid_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    /**
     * When false, bank payout paired lines are omitted from Z-report vouchers.
     * Stripe fees still post to configured payment_fee accounts; gift-card liability still posts when configured.
     * Defaults true for existing setups.
     */
    public static function zReportIncludesSettlement(PowerOfficeIntegration $integration): bool
    {
        $v = self::ledger($integration)['z_report_include_settlement'] ?? null;

        return $v === null ? true : (bool) $v;
    }
}
