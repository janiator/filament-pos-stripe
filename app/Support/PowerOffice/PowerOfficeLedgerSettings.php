<?php

namespace App\Support\PowerOffice;

use App\Models\PowerOfficeIntegration;

/**
 * Optional ledger routing stored on {@see PowerOfficeIntegration::$settings} under the `ledger` key.
 * Mirrors patterns from PSP settlement scripts (interim/liquid, per payment type debits, fee/payout pairs).
 */
final class PowerOfficeLedgerSettings
{
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
}
