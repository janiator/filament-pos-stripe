<?php

namespace App\Support\PowerOffice;

use App\Models\PosSession;
use App\Models\Vendor;

/**
 * Norwegian voucher line descriptions for PowerOffice Z-report sync.
 */
final class PowerOfficeLedgerLineDescriptions
{
    public static function voucher(PosSession $session): string
    {
        return 'POS Z-rapport '.$session->session_number;
    }

    public static function kasseBase(PosSession $session): string
    {
        if ($session->relationLoaded('posDevice')) {
            $deviceName = trim((string) ($session->posDevice?->device_name ?? ''));
            if ($deviceName !== '') {
                return 'Kasse '.$deviceName;
            }
        }

        $session->loadMissing(['posDevice.store', 'store']);

        $deviceName = trim((string) ($session->posDevice?->device_name ?? ''));
        if ($deviceName !== '') {
            return 'Kasse '.$deviceName;
        }

        $storeName = trim((string) ($session->posDevice?->store?->name ?? $session->store?->name ?? ''));
        if ($storeName !== '') {
            return 'Kasse '.$storeName;
        }

        return 'Kasse';
    }

    public static function payment(string $method, PosSession $session): string
    {
        $base = self::kasseBase($session);

        return self::isVippsMethod($method) ? $base.' Vipps' : $base;
    }

    public static function vendorName(Vendor $vendor): string
    {
        $name = trim((string) $vendor->name);

        return $name !== '' ? $name : 'Leverandør';
    }

    public static function vendorNameFromRow(?string $name): string
    {
        $name = trim((string) $name);

        return $name !== '' ? $name : 'Leverandør';
    }

    public static function aggregatedStuttreistCommission(): string
    {
        return self::categorySales('Stuttreist');
    }

    public static function vendorCommission(Vendor $vendor): string
    {
        return 'Salg '.self::vendorName($vendor);
    }

    public static function categorySales(string $label): string
    {
        $label = trim($label);

        return $label !== '' ? 'Salg '.$label : 'Salg';
    }

    public static function vatRateSales(string|int $rateKey): string
    {
        return 'Salg '.(int) $rateKey.' % mva';
    }

    public static function tips(): string
    {
        return 'Tips';
    }

    public static function rounding(): string
    {
        return 'Avrunding';
    }

    public static function vippsFees(): string
    {
        return 'Vipps gebyr';
    }

    public static function giftCardLiability(): string
    {
        return 'Gavekortsalg (gjeld)';
    }

    public static function paymentFeesSettlement(): string
    {
        return 'Betalingsgebyr';
    }

    public static function paymentFeesExpense(): string
    {
        return 'Betalingsgebyr';
    }

    public static function payoutSettlement(): string
    {
        return 'Utbetaling';
    }

    public static function payoutBank(): string
    {
        return 'Utbetaling (bank)';
    }

    public static function isVippsMethod(string $method): bool
    {
        $m = strtolower(trim($method));

        return $m === 'vipps' || str_contains($m, 'vipps');
    }
}
