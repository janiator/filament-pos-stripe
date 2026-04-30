<?php

namespace App\Actions\Stripe;

use App\Models\Store;
use App\Models\StoreStripeBalanceTransaction;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncStoreStripeBalanceTransactionsFromStripe
{
    /**
     * @return array{total: int, created: int, updated: int, errors: list<string>}
     */
    public function __invoke(Store $store, bool $notify = false): array
    {
        $result = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        try {
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

            $transactions = $stripe->balanceTransactions->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($transactions->autoPagingIterator() as $bt) {
                $result['total']++;

                try {
                    $chargeId = $this->resolveChargeId($bt);

                    $availableOn = null;
                    if (! empty($bt->available_on)) {
                        $availableOn = \Carbon\Carbon::createFromTimestamp((int) $bt->available_on);
                    }

                    $data = [
                        'store_id' => $store->id,
                        'stripe_account_id' => $stripeAccountId,
                        'stripe_balance_transaction_id' => $bt->id,
                        'type' => (string) $bt->type,
                        'amount' => (int) $bt->amount,
                        'fee' => max(0, (int) $bt->fee),
                        'net' => (int) $bt->net,
                        'currency' => (string) $bt->currency,
                        'status' => $bt->status ?? null,
                        'description' => $bt->description ?? null,
                        'stripe_charge_id' => $chargeId,
                        'stripe_created' => (int) $bt->created,
                        'available_on' => $availableOn,
                        'reporting_category' => $bt->reporting_category ?? null,
                    ];

                    $record = StoreStripeBalanceTransaction::query()
                        ->where('stripe_balance_transaction_id', $bt->id)
                        ->first();

                    if ($record) {
                        $record->fill($data);
                        $record->save();
                        $result['updated']++;
                    } else {
                        StoreStripeBalanceTransaction::query()->create($data);
                        $result['created']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Balance transaction {$bt->id}: {$e->getMessage()}";
                    report($e);
                }
            }

            if ($notify) {
                $this->sendResultNotification($result);
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

    protected function resolveChargeId(object $bt): ?string
    {
        if (($bt->type ?? null) !== 'charge') {
            return null;
        }

        $source = $bt->source ?? null;
        if (is_string($source) && str_starts_with($source, 'ch_')) {
            return $source;
        }

        if (is_object($source) && isset($source->id) && is_string($source->id) && str_starts_with($source->id, 'ch_')) {
            return $source->id;
        }

        return null;
    }

    /**
     * @param  array{total: int, created: int, updated: int, errors: list<string>}  $result
     */
    protected function sendResultNotification(array $result): void
    {
        if ($result['errors'] !== []) {
            $errorDetails = implode("\n", array_slice($result['errors'], 0, 5));
            if (count($result['errors']) > 5) {
                $errorDetails .= "\n... and ".(count($result['errors']) - 5).' more error(s)';
            }
            Notification::make()
                ->title('Sync completed with errors')
                ->body("Found {$result['total']} balance transactions. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Balance transactions synced')
            ->body("Found {$result['total']} balance transactions. {$result['created']} created, {$result['updated']} updated.")
            ->success()
            ->send();
    }
}
