<?php

namespace App\Support\PowerOffice;

use App\Enums\PowerOfficeVoucherPostingMode;
use App\Models\PowerOfficeIntegration;

final class PowerOfficePostingSettings
{
    public static function mode(PowerOfficeIntegration $integration): PowerOfficeVoucherPostingMode
    {
        $settings = $integration->settings;
        if (is_array($settings) && isset($settings['voucher_posting_mode'])) {
            $parsed = PowerOfficeVoucherPostingMode::tryFrom((string) $settings['voucher_posting_mode']);
            if ($parsed instanceof PowerOfficeVoucherPostingMode) {
                return $parsed;
            }
        }

        $globalPath = trim((string) config('poweroffice.ledger.post_path'));
        if (str_contains($globalPath, 'JournalEntryVouchers')) {
            return PowerOfficeVoucherPostingMode::JournalEntry;
        }

        return PowerOfficeVoucherPostingMode::Direct;
    }

    public static function usesDirectPosting(PowerOfficeIntegration $integration): bool
    {
        return self::mode($integration)->postsDirectlyToLedger();
    }

    public static function ledgerPostPath(PowerOfficeIntegration $integration): string
    {
        return self::mode($integration)->ledgerPostPath();
    }

    public static function modeFromDirectToggle(bool $directPostToLedger): PowerOfficeVoucherPostingMode
    {
        return $directPostToLedger
            ? PowerOfficeVoucherPostingMode::Direct
            : PowerOfficeVoucherPostingMode::JournalEntry;
    }
}
