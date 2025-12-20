<?php

namespace App\Actions\ConnectedCharges;

use App\Models\ConnectedCharge;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncConnectedChargesFromStripe
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

            // Get charges from the connected account
            $charges = $stripe->charges->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($charges->autoPagingIterator() as $charge) {
                $result['total']++;

                try {
                    // Ensure stripe_account_id is still valid
                    if (empty($stripeAccountId)) {
                        $result['errors'][] = "Charge {$charge->id}: stripe_account_id is empty (store: {$store->id})";
                        continue;
                    }

                    $data = [
                        'stripe_charge_id' => $charge->id,
                        'stripe_account_id' => $stripeAccountId, // Use refreshed value
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
                    ];

                    // Double-check stripe_account_id is not null
                    if (empty($data['stripe_account_id'])) {
                        $result['errors'][] = "Charge {$charge->id}: stripe_account_id is null after data preparation";
                        continue;
                    }

                    // Find by stripe_charge_id only (since it's unique)
                    // The same charge might exist with a different stripe_account_id
                    $chargeRecord = ConnectedCharge::where('stripe_charge_id', $charge->id)->first();

                    if ($chargeRecord) {
                        // Preserve POS-specific fields that aren't in Stripe data
                        $preservedFields = [
                            'pos_session_id' => $chargeRecord->pos_session_id,
                            'transaction_code' => $chargeRecord->transaction_code,
                            'payment_code' => $chargeRecord->payment_code,
                            'tip_amount' => $chargeRecord->tip_amount,
                            'article_group_code' => $chargeRecord->article_group_code,
                        ];
                        
                        // Update existing record - ensure stripe_account_id is set correctly
                        $chargeRecord->fill($data);
                        // Explicitly set stripe_account_id to ensure it's updated if it changed
                        $chargeRecord->stripe_account_id = $stripeAccountId;
                        
                        // Restore preserved fields (only if they were set, don't overwrite with null)
                        foreach ($preservedFields as $field => $value) {
                            if ($value !== null) {
                                $chargeRecord->$field = $value;
                            }
                        }
                        
                        $chargeRecord->save();
                        $result['updated']++;
                    } else {
                        // Create new record
                        ConnectedCharge::create($data);
                        $result['created']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Charge {$charge->id}: {$e->getMessage()}";
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
                        ->body("Found {$result['total']} charges. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Charges synced')
                        ->body("Found {$result['total']} charges. {$result['created']} created, {$result['updated']} updated.")
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
