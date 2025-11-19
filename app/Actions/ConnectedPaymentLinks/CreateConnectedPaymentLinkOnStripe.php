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

            // For destination links, add application fee
            if (isset($linkData['link_type']) && $linkData['link_type'] === 'destination') {
                if (isset($linkData['application_fee_percent'])) {
                    $stripeLinkData['application_fee_percent'] = $linkData['application_fee_percent'];
                }
                if (isset($linkData['application_fee_amount'])) {
                    $stripeLinkData['application_fee_amount'] = $linkData['application_fee_amount'];
                }
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

            // Create local mapping
            $linkRecord = ConnectedPaymentLink::create([
                'stripe_payment_link_id' => $paymentLink->id,
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
            ]);

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
        } catch (Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Payment link creation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            report($e);
            return null;
        }
    }
}

