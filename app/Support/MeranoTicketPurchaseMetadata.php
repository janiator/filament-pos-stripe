<?php

namespace App\Support;

class MeranoTicketPurchaseMetadata
{
    /**
     * @param  array<int, mixed>  $items
     * @return array{purchase_contains_tickets?: bool, purchase_ticket_reference?: string}
     */
    public static function fromCartItems(array $items): array
    {
        $refs = self::collectBookingReferences($items);

        if ($refs === []) {
            return [];
        }

        return [
            'purchase_contains_tickets' => true,
            'purchase_ticket_reference' => implode(',', $refs),
        ];
    }

    /**
     * Re-derive Merano ticket flags from the current cart line items.
     *
     * Unlike a merge-only helper, this clears stale ticket metadata when tickets
     * were removed (e.g. deferred cart revision).
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<int, mixed>  $items
     */
    public static function syncInto(array &$metadata, array $items): void
    {
        $derived = self::fromCartItems($items);

        if ($derived !== []) {
            $metadata = array_merge($metadata, $derived);

            return;
        }

        unset($metadata['purchase_contains_tickets'], $metadata['purchase_ticket_reference']);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<int, mixed>  $items
     */
    public static function mergeInto(array &$metadata, array $items): void
    {
        self::syncInto($metadata, $items);
    }

    public static function isTicketLineItem(array $item): bool
    {
        $itemMetadata = $item['metadata'] ?? null;

        if (! is_array($itemMetadata)) {
            $itemMetadata = [];
        }

        return isset($item['merano_booking_id'])
            || isset($item['merano_booking_number'])
            || isset($itemMetadata['merano_booking_id'])
            || isset($itemMetadata['merano_booking_number']);
    }

    /**
     * Shape line metadata for FlutterFlow's PurchaseItemMetadataStruct.
     *
     * FlutterFlow only preserves the known metadata fields (`notes`,
     * `custom_field`, `special_instructions`). Merano metadata is dynamic, so
     * encode it in `notes` while also keeping the raw keys for API clients.
     *
     * @return array<string, mixed>|null
     */
    public static function lineItemMetadataForResponse(array $item): ?array
    {
        $itemMetadata = $item['metadata'] ?? null;

        if (! is_array($itemMetadata) || $itemMetadata === []) {
            return null;
        }

        if (! self::isTicketLineItem($item)) {
            return $itemMetadata;
        }

        return array_merge($itemMetadata, [
            'notes' => json_encode($itemMetadata),
        ]);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    public static function collectBookingReferences(array $items): array
    {
        $refs = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! self::isTicketLineItem($item)) {
                continue;
            }

            $itemMetadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $number = $item['merano_booking_number'] ?? $itemMetadata['merano_booking_number'] ?? null;

            if (! is_string($number) && ! is_numeric($number)) {
                continue;
            }

            $trimmed = trim((string) $number);

            if ($trimmed !== '') {
                $refs[] = $trimmed;
            }
        }

        return array_values(array_unique($refs));
    }
}
