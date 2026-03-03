<?php

namespace App\Enums;

enum AddonType: string
{
    case WebflowCms = 'webflow_cms';
    case EventTickets = 'event_tickets';
    case GiftCards = 'gift_cards';
    case PaymentLinks = 'payment_links';
    case Transfers = 'transfers';
    case Workflows = 'workflows';
    case Pos = 'pos';

    public function label(): string
    {
        return match ($this) {
            self::WebflowCms => 'Webflow CMS',
            self::EventTickets => 'Event Tickets',
            self::GiftCards => 'Gift Cards',
            self::PaymentLinks => 'Payment Links',
            self::Transfers => 'Transfers',
            self::Workflows => 'Workflows',
            self::Pos => 'POS',
        };
    }

    /**
     * One-line description for the add-on card (what it enables).
     */
    public function description(): string
    {
        return match ($this) {
            self::WebflowCms => 'Link Webflow sites and manage CMS content from this store.',
            self::EventTickets => 'Sell event tickets with Webflow-driven content and Stripe payment links.',
            self::GiftCards => 'Create and manage gift cards for this store.',
            self::PaymentLinks => 'Create and manage Stripe payment links for checkout.',
            self::Transfers => 'View and manage Stripe transfers and payouts.',
            self::Workflows => 'Automate actions with event- and schedule-triggered workflows.',
            self::Pos => 'Point of sale: sessions, devices, terminals, receipts, and payment methods.',
        };
    }

    public function allowsWebflow(): bool
    {
        return match ($this) {
            self::WebflowCms, self::EventTickets => true,
            default => false,
        };
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
