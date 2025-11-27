<?php

namespace App\Actions\ConnectedPaymentMethods;

use App\Models\ConnectedPaymentMethod;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncConnectedPaymentMethodsFromStripe
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

            // Get all customers for this account, then get their payment methods
            $customers = $stripe->customers->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($customers->autoPagingIterator() as $customer) {
                try {
                    // Get payment methods for this customer
                    $paymentMethods = $stripe->paymentMethods->all(
                        ['customer' => $customer->id, 'type' => 'card'],
                        ['stripe_account' => $stripeAccountId]
                    );

                    foreach ($paymentMethods->autoPagingIterator() as $paymentMethod) {
                        $result['total']++;

                        try {
                            // Ensure stripe_account_id is still valid
                            if (empty($stripeAccountId)) {
                                $result['errors'][] = "Payment Method {$paymentMethod->id}: stripe_account_id is empty (store: {$store->id})";
                                continue;
                            }

                            $card = $paymentMethod->card ?? null;
                            $billing = $paymentMethod->billing_details ?? null;

                            $data = [
                                'stripe_payment_method_id' => $paymentMethod->id,
                                'stripe_account_id' => $stripeAccountId, // Use refreshed value
                                'stripe_customer_id' => $customer->id,
                                'type' => $paymentMethod->type,
                                'card_brand' => $card->brand ?? null,
                                'card_last4' => $card->last4 ?? null,
                                'card_exp_month' => $card->exp_month ?? null,
                                'card_exp_year' => $card->exp_year ?? null,
                                'billing_details_name' => $billing->name ?? null,
                                'billing_details_email' => $billing->email ?? null,
                                'billing_details_address' => $billing->address ? (array) $billing->address : null,
                                'is_default' => false, // Would need to check customer's default payment method
                                'metadata' => $paymentMethod->metadata ? (array) $paymentMethod->metadata : null,
                            ];

                            // Double-check stripe_account_id is not null before creating
                            if (empty($data['stripe_account_id'])) {
                                $result['errors'][] = "Payment Method {$paymentMethod->id}: stripe_account_id is null after data preparation";
                                continue;
                            }

                            $paymentMethodRecord = ConnectedPaymentMethod::where('stripe_payment_method_id', $paymentMethod->id)->first();

                            if ($paymentMethodRecord) {
                                $paymentMethodRecord->fill($data);
                        // Explicitly set stripe_account_id to ensure it's updated if it changed
                        $paymentMethodRecord->stripe_account_id = $stripeAccountId;
                        $paymentMethodRecord->save();
                                $result['updated']++;
                            } else {
                                ConnectedPaymentMethod::create($data);
                                $result['created']++;
                            }
                        } catch (Throwable $e) {
                            $result['errors'][] = "Payment Method {$paymentMethod->id}: {$e->getMessage()}";
                            report($e);
                        }
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Customer {$customer->id}: {$e->getMessage()}";
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
                        ->body("Found {$result['total']} payment methods. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Payment methods synced')
                        ->body("Found {$result['total']} payment methods. {$result['created']} created, {$result['updated']} updated.")
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

