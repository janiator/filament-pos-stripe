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
                'currency' => $chargeData['currency'] ?? 'usd',
                'customer' => $chargeData['stripe_customer_id'] ?? null,
                'payment_method' => $chargeData['stripe_payment_method_id'] ?? null,
                'description' => $chargeData['description'] ?? null,
                'metadata' => $chargeData['metadata'] ?? [],
            ];

            // Remove null values
            $stripeChargeData = array_filter($stripeChargeData, fn ($value) => ! is_null($value));

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
                'payment_method' => $charge->payment_method_details->type ?? null,
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

