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
     * @param  array<string, mixed>  $metadata
     * @param  array<int, mixed>  $items
     */
    public static function mergeInto(array &$metadata, array $items): void
    {
        if (($metadata['purchase_contains_tickets'] ?? false) === true
            && ! empty($metadata['purchase_ticket_reference'] ?? '')) {
            return;
        }

        $derived = self::fromCartItems($items);

        if ($derived !== []) {
            $metadata = array_merge($metadata, $derived);
        }
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
