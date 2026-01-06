<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Pages;

use App\Actions\ConnectedPaymentLinks\CreateConnectedPaymentLinkOnStripe;
use App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource;
use App\Models\ConnectedPaymentLink;
use App\Models\Store;
use Filament\Resources\Pages\CreateRecord;

class CreateConnectedPaymentLink extends CreateRecord
{
    protected static string $resource = ConnectedPaymentLinkResource::class;

    /**
     * Store the created payment link model instance.
     * This is set in mutateFormDataBeforeCreate and used in handleRecordCreation
     * to prevent duplicate creation attempts.
     */
    protected ?ConnectedPaymentLink $createdPaymentLink = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure stripe_account_id is set from tenant if not provided
        if (empty($data['stripe_account_id'])) {
            try {
                $tenant = \Filament\Facades\Filament::getTenant();
                $data['stripe_account_id'] = $tenant?->stripe_account_id;
            } catch (\Throwable $e) {
                // Fallback
            }
        }
        
        if (empty($data['stripe_account_id'])) {
            throw new \Exception('Store (stripe_account_id) is required to create a payment link.');
        }
        
        $store = Store::where('stripe_account_id', $data['stripe_account_id'])->firstOrFail();
        
        // Prepare line items for Stripe
        $lineItem = [
            'price' => $data['stripe_price_id'],
            'quantity' => 1,
        ];

        // Add adjustable quantity if enabled
        if (!empty($data['adjustable_quantity_enabled'])) {
            $lineItem['adjustable_quantity'] = [
                'enabled' => true,
            ];

            // Add minimum quantity if provided
            if (isset($data['adjustable_quantity_minimum']) && $data['adjustable_quantity_minimum'] !== null && $data['adjustable_quantity_minimum'] !== '') {
                $lineItem['adjustable_quantity']['minimum'] = (int) $data['adjustable_quantity_minimum'];
            }

            // Add maximum quantity if provided
            if (isset($data['adjustable_quantity_maximum']) && $data['adjustable_quantity_maximum'] !== null && $data['adjustable_quantity_maximum'] !== '') {
                $lineItem['adjustable_quantity']['maximum'] = (int) $data['adjustable_quantity_maximum'];
            }
        }

        $lineItems = [$lineItem];

        $linkData = [
            'line_items' => $lineItems,
            'name' => $data['name'] ?? null,
            'link_type' => $data['link_type'] ?? 'direct',
            'after_completion_redirect_url' => $data['after_completion_redirect_url'] ?? null,
        ];

        // Add application fee (works for both direct and destination links)
        // Check if the price is recurring
        $price = null;
        if (!empty($data['stripe_price_id'])) {
            $price = \App\Models\ConnectedPrice::where('stripe_price_id', $data['stripe_price_id'])
                ->where('stripe_account_id', $data['stripe_account_id'])
                ->first();
        }
        
        $isRecurring = $price && $price->type === 'recurring';
        
        if ($isRecurring) {
            // For recurring prices, use application_fee_percent
            if (isset($data['application_fee_percent']) && $data['application_fee_percent'] !== null && $data['application_fee_percent'] !== '') {
                $linkData['application_fee_percent'] = (float) $data['application_fee_percent'];
            }
            // Don't allow application_fee_amount for recurring prices
        } else {
            // For one-time prices, use application_fee_amount
            if (isset($data['application_fee_amount']) && $data['application_fee_amount'] !== null && $data['application_fee_amount'] !== '') {
                $linkData['application_fee_amount'] = (int) $data['application_fee_amount'];
            } elseif (isset($data['application_fee_percent']) && $data['application_fee_percent'] !== null && $data['application_fee_percent'] !== '') {
                // Convert percentage to amount in cents for one-time prices
                if ($price && $price->unit_amount) {
                    $feePercent = (float) $data['application_fee_percent'];
                    $feeAmount = (int) round(($price->unit_amount * $feePercent) / 100);
                    $linkData['application_fee_amount'] = $feeAmount;
                }
            }
        }

        $action = new CreateConnectedPaymentLinkOnStripe();
        $paymentLink = $action($store, $linkData, true);

        if (! $paymentLink) {
            throw new \Exception('Failed to create payment link on Stripe.');
        }

        // Store the created model instance to prevent duplicate creation
        $this->createdPaymentLink = $paymentLink;

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

    /**
     * Override record creation to return the already-created model instance.
     * The CreateConnectedPaymentLinkOnStripe action already creates/updates the record
     * using updateOrCreate, so we need to return that instance instead of creating a new one.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // If we already have a created payment link (from mutateFormDataBeforeCreate),
        // return it instead of creating a new one. This prevents unique constraint violations.
        if ($this->createdPaymentLink) {
            return $this->createdPaymentLink;
        }

        // Fallback to parent implementation if for some reason the model wasn't created
        return parent::handleRecordCreation($data);
    }
}
