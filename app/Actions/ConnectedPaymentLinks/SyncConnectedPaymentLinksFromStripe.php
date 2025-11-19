<?php

namespace App\Actions\ConnectedPaymentLinks;

use App\Models\ConnectedPaymentLink;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncConnectedPaymentLinksFromStripe
{
    public function __invoke(Store $store, bool $notify = false): array
    {
        $result = [
            'total'   => 0,
            'created' => 0,
            'updated' => 0,
            'errors'  => [],
        ];

        try {
            if (! $store->hasStripeAccount()) {
                if ($notify) {
                    Notification::make()
                        ->title('Store not connected')
                        ->body('This store is not connected to Stripe.')
                        ->danger()
                        ->send();
                }

                return $result;
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

                return $result;
            }

            $stripe = new StripeClient($secret);

            // Get payment links from the connected account
            // Note: Stripe API doesn't directly support filtering by connected account for payment links
            // We'll need to get all payment links and filter by metadata or store them with account_id in metadata
            $paymentLinks = $stripe->paymentLinks->all(
                ['limit' => 100],
                ['stripe_account' => $store->stripe_account_id]
            );

            foreach ($paymentLinks->autoPagingIterator() as $paymentLink) {
                $result['total']++;

                try {
                    // Get the first line item's price if available
                    $priceId = null;
                    if (isset($paymentLink->line_items->data[0])) {
                        $lineItem = $paymentLink->line_items->data[0];
                        $priceId = is_string($lineItem->price) ? $lineItem->price : ($lineItem->price->id ?? null);
                    }

                    $data = [
                        'stripe_payment_link_id' => $paymentLink->id,
                        'stripe_account_id' => $store->stripe_account_id,
                        'stripe_price_id' => $priceId,
                        'name' => $paymentLink->metadata->name ?? null,
                        'description' => null, // Payment links don't have direct description
                        'url' => $paymentLink->url,
                        'active' => $paymentLink->active ?? true,
                        'link_type' => 'direct', // Would need to check if it's destination based on application_fee
                        'application_fee_percent' => $paymentLink->application_fee_percent ?? null,
                        'application_fee_amount' => $paymentLink->application_fee_amount ?? null,
                        'after_completion_redirect_url' => $paymentLink->after_completion->redirect->url ?? null,
                        'line_items' => $paymentLink->line_items ? (array) $paymentLink->line_items : null,
                        'metadata' => $paymentLink->metadata ? (array) $paymentLink->metadata : null,
                    ];

                    $paymentLinkRecord = ConnectedPaymentLink::where('stripe_payment_link_id', $paymentLink->id)
                        ->where('stripe_account_id', $store->stripe_account_id)
                        ->first();

                    if ($paymentLinkRecord) {
                        $paymentLinkRecord->fill($data);
                        $paymentLinkRecord->save();
                        $result['updated']++;
                    } else {
                        ConnectedPaymentLink::create($data);
                        $result['created']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Payment Link {$paymentLink->id}: {$e->getMessage()}";
                    report($e);
                }
            }

            if ($notify) {
                if (! empty($result['errors'])) {
                    $errorDetails = implode("\n", array_slice($result['errors'], 0, 5));
                    if (count($result['errors']) > 5) {
                        $errorDetails .= "\n... and " . (count($result['errors']) - 5) . " more error(s)";
                    }
                    Notification::make()
                        ->title('Sync completed with errors')
                        ->body("Found {$result['total']} payment links. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Payment links synced')
                        ->body("Found {$result['total']} payment links. {$result['created']} created, {$result['updated']} updated.")
                        ->success()
                        ->send();
                }
            }

            return $result;
        } catch (AccountNotFoundException $e) {
            if ($notify) {
                Notification::make()
                    ->title('Sync failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return $result;
        } catch (Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Sync failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            report($e);
            return $result;
        }
    }
}

