<?php

namespace App\Filament\Resources\EventTickets\Concerns;

use App\Actions\EventTickets\CreateEventTicketPaymentLink;
use App\Models\ConnectedPaymentLink;
use App\Models\Store;
use Filament\Notifications\Notification;

trait ProcessesEventTicketPaymentLinks
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function processPaymentLinkModes(array $data, ?Store $store): array
    {
        if (! $store) {
            return $this->stripPaymentLinkTransientFields($data);
        }

        $createAction = app(CreateEventTicketPaymentLink::class);

        if (($data['ticket_1_payment_link_mode'] ?? 'existing') === 'new'
            && ! empty($data['ticket_1_new_label'])
            && isset($data['ticket_1_new_price_nok']) && (float) $data['ticket_1_new_price_nok'] > 0) {
            $priceCents = (int) round((float) $data['ticket_1_new_price_nok'] * 100);
            $link = $createAction($store, [
                'label' => $data['ticket_1_new_label'],
                'price_cents' => $priceCents,
                'max_quantity' => (int) ($data['ticket_1_available'] ?? 0),
                'image_url' => $data['image_url'] ?? null,
            ]);
            if ($link) {
                $data['ticket_1_payment_link_id'] = $link->stripe_payment_link_id;
                $data['ticket_1_price_id'] = $link->stripe_price_id;
            } else {
                Notification::make()
                    ->title('Ticket 1 payment link')
                    ->body('Failed to create payment link. Please try again or use an existing link.')
                    ->danger()
                    ->send();
            }
        } elseif (! empty($data['ticket_1_payment_link_id'])) {
            $pl = ConnectedPaymentLink::where('stripe_payment_link_id', $data['ticket_1_payment_link_id'])->first();
            if ($pl?->stripe_price_id) {
                $data['ticket_1_price_id'] = $pl->stripe_price_id;
            }
        }

        if (! ($data['ticket_2_enabled'] ?? true)) {
            $data['ticket_2_label'] = null;
            $data['ticket_2_available'] = 0;
            $data['ticket_2_payment_link_id'] = null;
            $data['ticket_2_price_id'] = null;
        } elseif (($data['ticket_2_payment_link_mode'] ?? 'existing') === 'new'
            && ! empty($data['ticket_2_new_label'])
            && isset($data['ticket_2_new_price_nok']) && (float) $data['ticket_2_new_price_nok'] > 0) {
            $priceCents = (int) round((float) $data['ticket_2_new_price_nok'] * 100);
            $link = $createAction($store, [
                'label' => $data['ticket_2_new_label'],
                'price_cents' => $priceCents,
                'max_quantity' => (int) ($data['ticket_2_available'] ?? 0),
                'image_url' => $data['image_url'] ?? null,
            ]);
            if ($link) {
                $data['ticket_2_payment_link_id'] = $link->stripe_payment_link_id;
                $data['ticket_2_price_id'] = $link->stripe_price_id;
            } else {
                Notification::make()
                    ->title('Ticket 2 payment link')
                    ->body('Failed to create payment link. Please try again or use an existing link.')
                    ->danger()
                    ->send();
            }
        } elseif (! empty($data['ticket_2_payment_link_id'])) {
            $pl = ConnectedPaymentLink::where('stripe_payment_link_id', $data['ticket_2_payment_link_id'])->first();
            if ($pl?->stripe_price_id) {
                $data['ticket_2_price_id'] = $pl->stripe_price_id;
            }
        }

        return $this->stripPaymentLinkTransientFields($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripPaymentLinkTransientFields(array $data): array
    {
        $transient = [
            'ticket_1_payment_link_mode',
            'ticket_1_new_label',
            'ticket_1_new_price_nok',
            'ticket_2_enabled',
            'ticket_2_payment_link_mode',
            'ticket_2_new_label',
            'ticket_2_new_price_nok',
        ];
        foreach ($transient as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
