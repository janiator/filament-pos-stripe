<?php

namespace App\Support\Tripletex;

use App\Models\TripletexIntegration;

/**
 * Default Tripletex Filament form values aligned with Merano-Tripletex-Sync `config.js` (ACCOUNT map).
 *
 * Applied only when the store has no account mapping rows and no payout routing saved yet, so existing
 * configurations are never overwritten on load.
 */
final class TripletexMeranoLegacyFormDefaults
{
    public static function shouldPrefill(TripletexIntegration $integration): bool
    {
        if ($integration->accountMappings()->exists()) {
            return false;
        }

        $settings = $integration->settings;
        if (! is_array($settings)) {
            return true;
        }

        $ledger = $settings['ledger'] ?? null;
        if (! is_array($ledger)) {
            return true;
        }

        $payout = $ledger['payout'] ?? null;
        if (! is_array($payout)) {
            return true;
        }

        return ! filled($payout['credit_account_no'] ?? null)
            && ! filled($payout['debit_bank_account_no'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $base  Form state keys as built by ManageTripletexIntegration::fillSettingsForm()
     * @return array<string, mixed>
     */
    public static function mergeWhenPristine(array $base, TripletexIntegration $integration): array
    {
        if (! self::shouldPrefill($integration)) {
            return $base;
        }

        $defaults = config('tripletex.default_form_state', []);
        if (! is_array($defaults)) {
            return $base;
        }

        foreach ($defaults as $key => $defaultValue) {
            if (! array_key_exists($key, $base)) {
                continue;
            }
            if (! self::isEmptyForDefaultMerge($base[$key])) {
                continue;
            }
            if (self::isEmptyForDefaultMerge($defaultValue)) {
                continue;
            }
            $base[$key] = is_string($defaultValue) ? trim($defaultValue) : $defaultValue;
        }

        return $base;
    }

    protected static function isEmptyForDefaultMerge(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if ($value === []) {
            return true;
        }

        return false;
    }
}
