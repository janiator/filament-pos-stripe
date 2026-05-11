<?php

namespace App\Support\Tripletex;

use App\Models\ConnectedCharge;
use App\Models\StoreStripeBalanceTransaction;
use App\Models\TripletexIntegration;

/**
 * Shared rules for which Connect charges are treated as external (web/advance) ticket sales
 * on Tripletex payout vouchers.
 */
final class TripletexExternalTicketSalesMatch
{
    /**
     * Merge Stripe charge metadata from the local mirror row and the expanded balance-transaction source.
     * {@see ConnectedCharge::$metadata} may only contain POS-enriched keys while {@see StoreStripeBalanceTransaction::$source_metadata}
     * still holds keys present on the Stripe Charge at sync time; union avoids dropping required keys (e.g. booking_id).
     *
     * @return array<string, mixed>
     */
    public static function mergedMetadata(ConnectedCharge $charge, StoreStripeBalanceTransaction $bt): array
    {
        $fromSource = is_array($bt->source_metadata) ? $bt->source_metadata : [];
        $fromCharge = is_array($charge->metadata) ? $charge->metadata : [];

        return array_merge($fromSource, $fromCharge);
    }

    public static function matches(
        TripletexIntegration $integration,
        ConnectedCharge $charge,
        StoreStripeBalanceTransaction $bt,
    ): bool {
        $meta = self::mergedMetadata($charge, $bt);

        foreach (TripletexLedgerSettings::externalTicketSalesRequireMetadataKeys($integration) as $key) {
            $v = $meta[$key] ?? null;
            if ($v === null || $v === '' || $v === []) {
                return false;
            }
        }

        $regex = TripletexLedgerSettings::externalTicketSalesDescriptionRegex($integration);
        if ($regex !== null) {
            $haystack = (string) ($charge->description ?? $bt->description ?? '');
            if (@preg_match($regex, $haystack) !== 1) {
                return false;
            }
        }

        return true;
    }
}
