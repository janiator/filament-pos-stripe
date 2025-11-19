<?php

namespace App\Actions\ConnectedTransfers;

use App\Models\ConnectedTransfer;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncConnectedTransfersFromStripe
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

            // Get transfers from the connected account
            $transfers = $stripe->transfers->all(
                ['limit' => 100],
                ['stripe_account' => $store->stripe_account_id]
            );

            foreach ($transfers->autoPagingIterator() as $transfer) {
                $result['total']++;

                try {
                    $data = [
                        'stripe_transfer_id' => $transfer->id,
                        'stripe_account_id' => $store->stripe_account_id,
                        'stripe_charge_id' => $transfer->source_transaction ?? null,
                        'stripe_payment_intent_id' => null, // Transfers don't directly link to payment intents
                        'amount' => $transfer->amount,
                        'currency' => $transfer->currency,
                        'status' => $transfer->status ?? 'pending',
                        'destination' => $transfer->destination ?? null,
                        'description' => $transfer->description,
                        'arrival_date' => $transfer->arrival_date ? date('Y-m-d H:i:s', $transfer->arrival_date) : null,
                        'metadata' => $transfer->metadata ? (array) $transfer->metadata : null,
                        'reversals' => $transfer->reversals ? (array) $transfer->reversals : null,
                        'reversed_amount' => $transfer->amount_reversed ?? 0,
                    ];

                    $transferRecord = ConnectedTransfer::where('stripe_transfer_id', $transfer->id)
                        ->where('stripe_account_id', $store->stripe_account_id)
                        ->first();

                    if ($transferRecord) {
                        $transferRecord->fill($data);
                        $transferRecord->save();
                        $result['updated']++;
                    } else {
                        ConnectedTransfer::create($data);
                        $result['created']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Transfer {$transfer->id}: {$e->getMessage()}";
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
                        ->body("Found {$result['total']} transfers. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Transfers synced')
                        ->body("Found {$result['total']} transfers. {$result['created']} created, {$result['updated']} updated.")
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

