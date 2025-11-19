<?php

namespace App\Actions\ConnectedCharges;

use App\Models\ConnectedCharge;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class CreateConnectedChargeOnStripe
{
    public function __invoke(Store $store, array $chargeData, bool $notify = false): ?ConnectedCharge
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

            // Prepare charge data for Stripe
            $stripeChargeData = [
                'amount' => $chargeData['amount'], // Amount in cents
                'currency' => strtolower($chargeData['currency'] ?? 'usd'),
                'customer' => $chargeData['stripe_customer_id'] ?? null,
                'payment_method' => $chargeData['stripe_payment_method_id'] ?? null,
                'description' => $chargeData['description'] ?? null,
                'metadata' => $chargeData['metadata'] ?? [],
            ];

            // Remove null values (but keep empty arrays for metadata)
            $stripeChargeData = array_filter($stripeChargeData, function ($value, $key) {
                if ($key === 'metadata') {
                    return true; // Always include metadata, even if empty
                }
                return ! is_null($value);
            }, ARRAY_FILTER_USE_BOTH);
            
            // Stripe requires either customer or payment_method for charges
            if (empty($stripeChargeData['customer']) && empty($stripeChargeData['payment_method'])) {
                if ($notify) {
                    Notification::make()
                        ->title('Charge creation failed')
                        ->body('Either a customer or payment method must be provided to create a charge.')
                        ->danger()
                        ->send();
                }
                return null;
            }
            
            // If customer is provided but no payment method, check if customer has a default payment method
            if (!empty($stripeChargeData['customer']) && empty($stripeChargeData['payment_method'])) {
                // Try to get the customer's default payment method from Stripe
                try {
                    $customer = $stripe->customers->retrieve(
                        $stripeChargeData['customer'],
                        ['stripe_account' => $store->stripe_account_id]
                    );
                    
                    // Check if customer has a default payment method
                    if (empty($customer->invoice_settings->default_payment_method) && empty($customer->default_source)) {
                        if ($notify) {
                            Notification::make()
                                ->title('Charge creation failed')
                                ->body('The selected customer does not have a default payment method. Please select a payment method for this charge.')
                                ->danger()
                                ->send();
                        }
                        return null;
                    }
                } catch (Throwable $e) {
                    // If we can't retrieve the customer, proceed and let Stripe handle the error
                    // This might be a permissions issue or the customer might not exist
                }
            }

            // Create charge on connected account
            $charge = $stripe->charges->create(
                $stripeChargeData,
                ['stripe_account' => $store->stripe_account_id]
            );

            // Create local mapping
            $chargeRecord = ConnectedCharge::create([
                'stripe_charge_id' => $charge->id,
                'stripe_account_id' => $store->stripe_account_id,
                'stripe_customer_id' => $charge->customer ?? null,
                'stripe_payment_intent_id' => $charge->payment_intent ?? null,
                'amount' => $charge->amount,
                'amount_refunded' => $charge->amount_refunded ?? 0,
                'currency' => $charge->currency,
                'status' => $charge->status,
                'payment_method' => $charge->payment_method_details?->type ?? null,
                'description' => $charge->description,
                'failure_code' => $charge->failure_code,
                'failure_message' => $charge->failure_message,
                'captured' => $charge->captured ?? true,
                'refunded' => $charge->refunded ?? false,
                'paid' => $charge->paid ?? false,
                'paid_at' => $charge->created ? date('Y-m-d H:i:s', $charge->created) : null,
                'metadata' => $charge->metadata ? (array) $charge->metadata : null,
                'outcome' => $charge->outcome ? (array) $charge->outcome : null,
                'charge_type' => $charge->on_behalf_of ? 'destination' : 'direct',
                'application_fee_amount' => $charge->application_fee_amount ?? null,
            ]);

            if ($notify) {
                Notification::make()
                    ->title('Charge created')
                    ->body("Charge {$charge->id} created successfully.")
                    ->success()
                    ->send();
            }

            return $chargeRecord;
        } catch (AccountNotFoundException $e) {
            if ($notify) {
                Notification::make()
                    ->title('Charge creation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return null;
        } catch (Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Charge creation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            report($e);
            return null;
        }
    }
}

