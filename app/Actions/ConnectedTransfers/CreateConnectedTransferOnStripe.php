<?php

namespace App\Actions\ConnectedTransfers;

use App\Models\ConnectedTransfer;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class CreateConnectedTransferOnStripe
{
    public function __invoke(Store $store, array $transferData, bool $notify = false): ?ConnectedTransfer
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

            // Prepare transfer data for Stripe
            $stripeTransferData = [
                'amount' => $transferData['amount'], // Amount in cents
                'currency' => $transferData['currency'] ?? 'usd',
                'destination' => $transferData['destination'] ?? $store->stripe_account_id,
                'description' => $transferData['description'] ?? null,
                'metadata' => $transferData['metadata'] ?? [],
            ];

            // Remove null values
            $stripeTransferData = array_filter($stripeTransferData, fn ($value) => ! is_null($value));

            // Create transfer
            $transfer = $stripe->transfers->create(
                $stripeTransferData
            );

            // Create local mapping
            $transferRecord = ConnectedTransfer::create([
                'stripe_transfer_id' => $transfer->id,
                'stripe_account_id' => $store->stripe_account_id,
                'stripe_charge_id' => $transfer->source_transaction ?? null,
                'stripe_payment_intent_id' => null,
                'amount' => $transfer->amount,
                'currency' => $transfer->currency,
                'status' => $transfer->status ?? 'pending',
                'destination' => $transfer->destination ?? null,
                'description' => $transfer->description,
                'arrival_date' => $transfer->arrival_date ? date('Y-m-d H:i:s', $transfer->arrival_date) : null,
                'metadata' => $transfer->metadata ? (array) $transfer->metadata : null,
                'reversals' => $transfer->reversals ? (array) $transfer->reversals : null,
                'reversed_amount' => $transfer->amount_reversed ?? 0,
            ]);

            if ($notify) {
                Notification::make()
                    ->title('Transfer created')
                    ->body("Transfer {$transfer->id} created successfully.")
                    ->success()
                    ->send();
            }

            return $transferRecord;
        } catch (AccountNotFoundException $e) {
            if ($notify) {
                Notification::make()
                    ->title('Transfer creation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            return null;
        } catch (Throwable $e) {
            if ($notify) {
                Notification::make()
                    ->title('Transfer creation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

            report($e);
            return null;
        }
    }
}

