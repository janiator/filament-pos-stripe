<?php

namespace App\Actions\EventTickets;

use Carbon\Carbon;
use Positiv\FilamentWebflow\Models\WebflowItem;

class MapWebflowItemToEventTicketData
{
    /**
     * Map a Webflow CMS item's field_data to EventTicket attributes.
     * Used by import and by the Event Ticket form to prefill from Webflow.
     *
     * @return array<string, mixed>
     */
    public static function map(WebflowItem $item): array
    {
        $fd = $item->field_data ?? [];
        $get = fn (array $keys) => self::getFirstMatch($fd, $keys);

        $eventDate = $get(['datofelt', 'event_date', 'Event Date']);
        if (is_string($eventDate)) {
            try {
                $eventDate = Carbon::parse($eventDate);
            } catch (\Throwable $e) {
                $eventDate = null;
            }
        }

        $imageUrl = $get(['bilde', 'image', 'Bilde']);
        if (is_array($imageUrl)) {
            $imageUrl = $imageUrl['url'] ?? $imageUrl['fileUrl'] ?? $imageUrl['src'] ?? null;
        }

        return [
            'name' => $get(['name', 'Name']) ?? 'Untitled',
            'slug' => $get(['slug', 'Slug']),
            'description' => $get(['beskrivelse', 'description', 'Beskrivelse']),
            'image_url' => is_string($imageUrl) ? $imageUrl : null,
            'event_date' => $eventDate,
            'event_time' => $get(['klokkeslett', 'event_time', 'Klokkeslett']),
            'venue' => $get(['venue', 'sted', 'Venue']),
            'ticket_1_label' => 'Billett 1',
            'ticket_1_available' => self::intOrNull($get(['billett-1-tilgjengelig', 'Billett 1: Tilgjengelig'])),
            'ticket_1_sold' => (int) ($get(['billett-1-solgte', 'Billett 1: Solgte']) ?? 0),
            'ticket_1_payment_link_id' => $get(['payment-link-id-1', 'Payment link id 1']),
            'ticket_1_price_id' => $get(['price-id-1', 'Price id 1']),
            'ticket_2_label' => 'Billett 2',
            'ticket_2_available' => self::intOrNull($get(['billett-2-tilgjengelig', 'Billett 2: Tilgjengelig'])),
            'ticket_2_sold' => (int) ($get(['billett-2-solgte', 'Billett 2: Solgte']) ?? 0),
            'ticket_2_payment_link_id' => $get(['payment-link-id-2', 'Payment link id 2']),
            'ticket_2_price_id' => $get(['price-id-2', 'Price id 2']),
            'is_sold_out' => filter_var($get(['utsolgt', 'Utsolgt']), FILTER_VALIDATE_BOOLEAN),
            'is_archived' => $item->is_archived ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     */
    private static function getFirstMatch(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
