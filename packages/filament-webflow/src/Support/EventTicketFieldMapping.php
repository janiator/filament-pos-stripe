<?php

namespace Positiv\FilamentWebflow\Support;

use Positiv\FilamentWebflow\Models\WebflowCollection;

class EventTicketFieldMapping
{
    /**
     * Default CMS field slugs per logical key (e.g. Arrangementers-style collections).
     *
     * @return array<string, string>
     */
    public static function defaultMapping(): array
    {
        return [
            'name' => 'name',
            'slug' => 'slug',
            'description' => 'beskrivelse',
            'image' => 'bilde',
            'event_date' => 'datofelt',
            'event_time' => 'klokkeslett',
            'venue' => 'venue',
            'ticket_1_available' => 'billett-1-tilgjengelig',
            'ticket_1_sold' => 'billett-1-solgte',
            'ticket_2_available' => 'billett-2-tilgjengelig',
            'ticket_2_sold' => 'billett-2-solgte',
            'is_sold_out' => 'utsolgt',
            'payment_link_id_1' => 'payment-link-id-1',
            'payment_link_id_2' => 'payment-link-id-2',
            'price_id_1' => 'price-id-1',
            'price_id_2' => 'price-id-2',
        ];
    }

    /**
     * Logical keys that can be mapped in the event tickets field-mapping modal.
     *
     * @return array<int, string>
     */
    public static function logicalKeys(): array
    {
        return array_keys(self::defaultMapping());
    }

    /**
     * Resolve the CMS field slug for a logical key using collection's field_mapping or default.
     */
    public static function resolveSlug(WebflowCollection $collection, string $logicalKey): ?string
    {
        $mapping = $collection->field_mapping ?? [];
        if (is_array($mapping) && array_key_exists($logicalKey, $mapping) && $mapping[$logicalKey] !== null && $mapping[$logicalKey] !== '') {
            return $mapping[$logicalKey];
        }

        $defaults = self::defaultMapping();

        return $defaults[$logicalKey] ?? null;
    }
}
