<?php

namespace App\Support\Tripletex;

use App\Models\TripletexIntegration;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;

/**
 * Optional ledger routing stored on {@see TripletexIntegration::$settings} under the `ledger` key.
 * Same shape as {@see \App\Support\PowerOffice\PowerOfficeLedgerSettings} for familiarity.
 */
final class TripletexLedgerSettings
{
    /**
     * @return array<string, mixed>
     */
    public static function ledger(TripletexIntegration $integration): array
    {
        $settings = $integration->settings;

        return is_array($settings) && isset($settings['ledger']) && is_array($settings['ledger'])
            ? $settings['ledger']
            : [];
    }

    public static function defaultSalesAccount(TripletexIntegration $integration): ?string
    {
        $v = self::ledger($integration)['default_sales_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    public static function paymentDebitAccount(TripletexIntegration $integration, string $method): ?string
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
    public static function paymentFeeAccounts(TripletexIntegration $integration): array
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
    public static function payoutAccounts(TripletexIntegration $integration): array
    {
        $block = self::ledger($integration)['payout'] ?? [];

        return [
            'credit' => filled($block['credit_account_no'] ?? null) ? (string) $block['credit_account_no'] : null,
            'debit' => filled($block['debit_bank_account_no'] ?? null) ? (string) $block['debit_bank_account_no'] : null,
        ];
    }

    public static function giftcardLiabilityAccount(TripletexIntegration $integration): ?string
    {
        $v = self::ledger($integration)['giftcard_liability_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    public static function appFeeSupplierId(TripletexIntegration $integration): ?int
    {
        $v = self::ledger($integration)['app_fee_supplier_id'] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    public static function zReportSplitLinesByCalendarDay(TripletexIntegration $integration): bool
    {
        return filter_var(self::ledger($integration)['z_report_split_lines_by_calendar_day'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Tripletex VAT type ids keyed by sales basis (e.g. VAT rate key "25", "15", "0").
     *
     * @return array<string, int>
     */
    public static function tripletexVatTypeSalesMap(TripletexIntegration $integration): array
    {
        $block = self::ledger($integration)['tripletex_vat_type_sales'] ?? [];
        if (! is_array($block)) {
            return [];
        }
        $out = [];
        foreach ($block as $k => $v) {
            if (is_numeric($v)) {
                $out[(string) $k] = (int) $v;
            }
        }

        return $out;
    }

    public static function tripletexVatTypeIdForSalesBasisKey(TripletexIntegration $integration, string $basisKey): ?int
    {
        $map = self::tripletexVatTypeSalesMap($integration);
        if ($map === []) {
            return null;
        }
        $key = (string) $basisKey;
        $canonical = PowerOfficeStandardVatRates::canonicalVatBasisKey($key);
        if ($canonical !== null) {
            return $map[$canonical] ?? $map[(string) (int) $canonical] ?? null;
        }

        return $map[$key] ?? null;
    }

    public static function tripletexVatTypeOutputVat(TripletexIntegration $integration): ?int
    {
        $v = self::ledger($integration)['tripletex_vat_type_output_vat'] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    public static function applicationFeeDebitAccount(TripletexIntegration $integration): ?string
    {
        $block = self::ledger($integration)['application_fee'] ?? [];
        if (! is_array($block)) {
            return null;
        }

        $v = $block['debit_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function externalTicketSalesSettings(TripletexIntegration $integration): array
    {
        $block = self::ledger($integration)['external_ticket_sales'] ?? [];

        return is_array($block) ? $block : [];
    }

    public static function externalTicketSalesEnabled(TripletexIntegration $integration): bool
    {
        return filter_var(self::externalTicketSalesSettings($integration)['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function externalTicketSalesAccountNo(TripletexIntegration $integration): ?string
    {
        $v = self::externalTicketSalesSettings($integration)['sales_account_no'] ?? null;

        return filled($v) ? (string) $v : null;
    }

    public static function externalTicketSalesClearingAccountNo(TripletexIntegration $integration): ?string
    {
        $v = self::externalTicketSalesSettings($integration)['clearing_account_no'] ?? null;
        if (filled($v)) {
            return (string) $v;
        }

        return self::payoutAccounts($integration)['credit'];
    }

    public static function externalTicketSalesVatTypeId(TripletexIntegration $integration): ?int
    {
        $v = self::externalTicketSalesSettings($integration)['tripletex_vat_type_id'] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    /**
     * When external ticket sales omits `require_metadata_keys` in ledger settings, payout matching uses
     * {@see self::externalTicketSalesDefaultAnyOfKeys()} (any non-empty key qualifies). When the integration stores a non-empty list,
     * every listed key must be present (AND).
     *
     * @return list<string>|null
     */
    public static function externalTicketSalesExplicitRequireMetadataKeys(TripletexIntegration $integration): ?array
    {
        $keys = self::externalTicketSalesSettings($integration)['require_metadata_keys'] ?? null;
        if (! is_array($keys)) {
            return null;
        }
        $out = [];
        foreach ($keys as $k) {
            if (is_string($k) && trim($k) !== '') {
                $out[] = trim($k);
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * Default “web or advance ticket” identifiers on Stripe metadata (OR): {@code booking_id}, camelCase {@code eventKey},
     * or snake_case {@code event_key} from some web checkouts.
     *
     * @return list<string>
     */
    public static function externalTicketSalesDefaultAnyOfKeys(): array
    {
        return ['booking_id', 'eventKey', 'event_key'];
    }

    public static function externalTicketSalesDescriptionRegex(TripletexIntegration $integration): ?string
    {
        $v = self::externalTicketSalesSettings($integration)['description_regex'] ?? null;

        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}
