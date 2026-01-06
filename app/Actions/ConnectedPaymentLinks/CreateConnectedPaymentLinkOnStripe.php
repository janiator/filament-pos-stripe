<?php

namespace App\Actions\ConnectedPaymentLinks;

use App\Models\ConnectedPaymentLink;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class CreateConnectedPaymentLinkOnStripe
{
    public function __invoke(Store $store, array $linkData, bool $notify = false): ?ConnectedPaymentLink
    {
        try {
            if (! $store->hasStripeAccount()) {
                if ($notify) {
                    Notification::make()
                        ->title('Store not connected')
                        ->body('This store is not connected to Stripe.')
                        ->danger()
                        ->send();
                }

                return null;
            }

            $secret = config('cashier.secret') ?? config('services.stripe.secret');

            if (! $secret) {
                if ($notify) {
                    Notification::make()
                        ->title('Stripe not configured')
                        ->body('No Stripe secret key found.')
                        ->danger()
                        ->send();
                }

                return null;
            }

            $stripe = new StripeClient($secret);

            // Prepare payment link data for Stripe
            $stripeLinkData = [
                'line_items' => $linkData['line_items'] ?? [],
            ];

            if (isset($linkData['name'])) {
                $stripeLinkData['metadata'] = ['name' => $linkData['name']];
            }

            if (isset($linkData['after_completion_redirect_url'])) {
                $stripeLinkData['after_completion'] = [
                    'type' => 'redirect',
                    'redirect' => [
                        'url' => $linkData['after_completion_redirect_url'],
                    ],
                ];
            }

            // Add application fee (works for both direct and destination links)
            // Note: application_fee_percent can only be used with recurring prices
            // application_fee_amount can only be used with one-time prices
            // Only set one fee type - the CreateConnectedPaymentLink page ensures the correct one is set
            if (isset($linkData['application_fee_percent'])) {
                $stripeLinkData['application_fee_percent'] = $linkData['application_fee_percent'];
            } elseif (isset($linkData['application_fee_amount'])) {
                $stripeLinkData['application_fee_amount'] = $linkData['application_fee_amount'];
            }

            // Create payment link on connected account
            $paymentLink = $stripe->paymentLinks->create(
                $stripeLinkData,
                ['stripe_account' => $store->stripe_account_id]
            );

            // Get the first line item's price if available
            $priceId = null;
            if (isset($paymentLink->line_items->data[0])) {
                $lineItem = $paymentLink->line_items->data[0];
                $priceId = is_string($lineItem->price) ? $lineItem->price : ($lineItem->price->id ?? null);
            }

            // Create or update local mapping
            $linkRecord = ConnectedPaymentLink::updateOrCreate(
                [
                    'stripe_payment_link_id' => $paymentLink->id,
                ],
                [
                    'stripe_account_id' => $store->stripe_account_id,
                    'stripe_price_id' => $priceId,
                    'name' => $paymentLink->metadata?->name ?? $linkData['name'] ?? null,
                    'description' => null,
                    'url' => $paymentLink->url,
                    'active' => $paymentLink->active ?? true,
                    'link_type' => $linkData['link_type'] ?? 'direct',
                    'application_fee_percent' => $paymentLink->application_fee_percent ?? null,
                    'application_fee_amount' => $paymentLink->application_fee_amount ?? null,
                    'after_completion_redirect_url' => $paymentLink->after_completion?->redirect?->url ?? null,
                    'line_items' => $paymentLink->line_items ? (array) $paymentLink->line_items : null,
                    'metadata' => $paymentLink->metadata ? (array) $paymentLink->metadata : null,
                ]
            );

            if ($notify) {
                Notification::make()
                    ->title('Payment link created')
                    ->body("Payment link {$paymentLink->id} created successfully.")
                    ->success()
                    ->send();
            }

            return $linkRecord;
        } catch (AccountNotFoundException $e) {
            if ($notify) {
                Notification::make()
                    ->title('Payment link creation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return null;
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $errorMessage = $e->getMessage();
            
            // Provide more user-friendly error messages for common Stripe errors
            if (str_contains($errorMessage, 'inactive product')) {
                $errorMessage = 'Cannot create payment link: The product associated with this price is not active. Please activate the product in Stripe first.';
            }
            
            if ($notify) {
                Notification::make()
                    ->title('Payment link creation failed')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
            }
            
            \Log::error('Failed to create payment link on Stripe', [
                'error' => $errorMessage,
                'stripe_error' => $e->getMessage(),
                'store_id' => $store->id,
                'link_data' => $linkData,
            ]);

            report($e);
            return null;
        } catch (Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Payment link creation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            \Log::error('Failed to create payment link on Stripe', [
                'error' => $e->getMessage(),
                'store_id' => $store->id,
                'link_data' => $linkData,
            ]);

            report($e);
            return null;
        }
    }
}

