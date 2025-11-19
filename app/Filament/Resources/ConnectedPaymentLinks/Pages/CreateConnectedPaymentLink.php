<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Pages;

use App\Actions\ConnectedPaymentLinks\CreateConnectedPaymentLinkOnStripe;
use App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource;
use App\Models\Store;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedPaymentLink extends CreateRecord
{
    protected static string $resource = ConnectedPaymentLinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $store = Store::where('stripe_account_id', $data['stripe_account_id'])->firstOrFail();
        
        // Prepare line items for Stripe
        $lineItems = [
            [
                'price' => $data['stripe_price_id'],
                'quantity' => 1,
            ],
        ];

        $linkData = [
            'line_items' => $lineItems,
            'name' => $data['name'] ?? null,
            'link_type' => $data['link_type'] ?? 'direct',
            'after_completion_redirect_url' => $data['after_completion_redirect_url'] ?? null,
        ];

        // Add application fee for destination links
        if ($linkData['link_type'] === 'destination') {
            if (isset($data['application_fee_percent'])) {
                $linkData['application_fee_percent'] = $data['application_fee_percent'];
            }
            if (isset($data['application_fee_amount'])) {
                $linkData['application_fee_amount'] = $data['application_fee_amount'];
            }
        }

        $action = new CreateConnectedPaymentLinkOnStripe();
        $paymentLink = $action($store, $linkData, true);

        if (! $paymentLink) {
            throw new \Exception('Failed to create payment link on Stripe.');
        }

        return [
            'stripe_payment_link_id' => $paymentLink->stripe_payment_link_id,
            'stripe_account_id' => $paymentLink->stripe_account_id,
            'stripe_price_id' => $paymentLink->stripe_price_id,
            'name' => $paymentLink->name,
            'description' => $paymentLink->description,
            'url' => $paymentLink->url,
            'active' => $paymentLink->active,
            'link_type' => $paymentLink->link_type,
            'application_fee_percent' => $paymentLink->application_fee_percent,
            'application_fee_amount' => $paymentLink->application_fee_amount,
            'after_completion_redirect_url' => $paymentLink->after_completion_redirect_url,
            'line_items' => $paymentLink->line_items,
            'metadata' => $paymentLink->metadata,
        ];
    }
}
