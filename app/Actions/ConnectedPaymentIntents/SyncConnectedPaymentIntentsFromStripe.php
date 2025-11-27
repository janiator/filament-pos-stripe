<?php

namespace App\Actions\ConnectedPaymentIntents;

use App\Models\ConnectedPaymentIntent;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncConnectedPaymentIntentsFromStripe
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
            // Refresh store to ensure we have the latest stripe_account_id
            $store->refresh();
            $stripeAccountId = $store->stripe_account_id;

            if (empty($stripeAccountId) || ! $store->hasStripeAccount()) {
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

            // Get payment intents from the connected account
            $paymentIntents = $stripe->paymentIntents->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($paymentIntents->autoPagingIterator() as $intent) {
                $result['total']++;

                try {
                    // Ensure stripe_account_id is still valid
                    if (empty($stripeAccountId)) {
                        $result['errors'][] = "Payment Intent {$intent->id}: stripe_account_id is empty (store: {$store->id})";
                        continue;
                    }

                    $data = [
                        'stripe_id' => $intent->id,
                        'stripe_account_id' => $stripeAccountId, // Use refreshed value
                        'stripe_customer_id' => $intent->customer ?? null,
                        'stripe_payment_method_id' => $intent->payment_method ?? null,
                        'amount' => $intent->amount,
                        'currency' => $intent->currency,
                        'status' => $intent->status,
                        'capture_method' => $intent->capture_method ?? 'automatic',
                        'confirmation_method' => $intent->confirmation_method ?? 'automatic',
                        'description' => $intent->description,
                        'receipt_email' => $intent->receipt_email,
                        'statement_descriptor' => $intent->statement_descriptor,
                        'statement_descriptor_suffix' => $intent->statement_descriptor_suffix,
                        'metadata' => $intent->metadata ? (array) $intent->metadata : null,
                        'payment_method_options' => $intent->payment_method_options ? (array) $intent->payment_method_options : null,
                        'client_secret' => $intent->client_secret,
                        'canceled_at' => $intent->canceled_at ? date('Y-m-d H:i:s', $intent->canceled_at) : null,
                        'cancellation_reason' => $intent->cancellation_reason,
                        'succeeded_at' => $intent->status === 'succeeded' && isset($intent->created) ? date('Y-m-d H:i:s', $intent->created) : null,
                    ];

                    // Double-check stripe_account_id is not null
                    if (empty($data['stripe_account_id'])) {
                        $result['errors'][] = "Payment Intent {$intent->id}: stripe_account_id is null after data preparation";
                        continue;
                    }

                    $intentRecord = ConnectedPaymentIntent::where('stripe_id', $intent->id)->first();

                    if ($intentRecord) {
                        $intentRecord->fill($data);
                        // Explicitly set stripe_account_id to ensure it's updated if it changed
                        $intentRecord->stripe_account_id = $stripeAccountId;
                        $intentRecord->save();
                        $result['updated']++;
                    } else {
                        ConnectedPaymentIntent::create($data);
                        $result['created']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Payment Intent {$intent->id}: {$e->getMessage()}";
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
                        ->body("Found {$result['total']} payment intents. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Payment intents synced')
                        ->body("Found {$result['total']} payment intents. {$result['created']} created, {$result['updated']} updated.")
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

