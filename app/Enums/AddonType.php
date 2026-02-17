<?php

namespace App\Enums;

enum AddonType: string
{
    case WebflowCms = 'webflow_cms';
    case EventTickets = 'event_tickets';

    public function label(): string
    {
        return match ($this) {
            self::WebflowCms => 'Webflow CMS',
            self::EventTickets => 'Event Tickets',
        };
    }

    public function allowsWebflow(): bool
    {
        return true;
    }

    /**
     * Addon type slugs that allow linking Webflow sites.
     *
     * @return array<string>
     */
    public static function typesWithWebflow(): array
    {
        return [
            self::WebflowCms->value,
            self::EventTickets->value,
        ];
    }
}
