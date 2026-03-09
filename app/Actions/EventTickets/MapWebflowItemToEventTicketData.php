<?php

namespace App\Actions\EventTickets;

use Carbon\Carbon;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Models\WebflowItem;
use Positiv\FilamentWebflow\Support\EventTicketFieldMapping;

class MapWebflowItemToEventTicketData
{
    /**
     * Map a Webflow CMS item's field_data to EventTicket attributes.
     * Used by import and by the Event Ticket form to prefill from Webflow.
     *
     * @return array<string, mixed>
     */
    public static function map(WebflowItem $item, ?WebflowCollection $collection = null): array
    {
        $collection = $collection ?? ($item->relationLoaded('collection') ? $item->collection : null);
        $fd = $item->field_data ?? [];
        $slug = $collection
            ? fn (string $logicalKey): ?string => EventTicketFieldMapping::resolveSlug($collection, $logicalKey)
            : null;
        $get = function (array $keys) use ($fd): mixed {
            return self::getFirstMatch($fd, $keys);
        };
        $getMapped = function (string $logicalKey) use ($fd, $slug): mixed {
            if ($slug !== null) {
                $s = $slug($logicalKey);
                if ($s !== null && array_key_exists($s, $fd)) {
                    return $fd[$s];
                }
            }
            $defaults = EventTicketFieldMapping::defaultMapping();
            $keys = [$defaults[$logicalKey] ?? $logicalKey];

            return self::getFirstMatch($fd, $keys);
        };

        $eventDate = $getMapped('event_date');
        if (is_string($eventDate)) {
            try {
                $eventDate = Carbon::parse($eventDate);
            } catch (\Throwable $e) {
                $eventDate = null;
            }
        }

        $imageUrl = $getMapped('image');
        if (is_array($imageUrl)) {
            $imageUrl = $imageUrl['url'] ?? $imageUrl['fileUrl'] ?? $imageUrl['src'] ?? null;
        }

        return [
            'name' => $getMapped('name') ?? 'Untitled',
            'slug' => $getMapped('slug'),
            'description' => $getMapped('description'),
            'image_url' => is_string($imageUrl) ? $imageUrl : null,
            'event_date' => $eventDate,
            'event_time' => $getMapped('event_time'),
            'venue' => $getMapped('venue'),
            'ticket_1_label' => 'Billett 1',
            'ticket_1_available' => self::intOrNull($getMapped('ticket_1_available')),
            'ticket_1_sold' => (int) ($getMapped('ticket_1_sold') ?? 0),
            'ticket_1_payment_link_id' => $getMapped('payment_link_id_1'),
            'ticket_1_price_id' => $getMapped('price_id_1'),
            'ticket_2_label' => 'Billett 2',
            'ticket_2_available' => self::intOrNull($getMapped('ticket_2_available')),
            'ticket_2_sold' => (int) ($getMapped('ticket_2_sold') ?? 0),
            'ticket_2_payment_link_id' => $getMapped('payment_link_id_2'),
            'ticket_2_price_id' => $getMapped('price_id_2'),
            'is_sold_out' => filter_var($getMapped('is_sold_out'), FILTER_VALIDATE_BOOLEAN),
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
