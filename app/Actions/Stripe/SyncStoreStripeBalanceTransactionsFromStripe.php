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
                [
                    'limit' => 100,
                    'expand' => ['data.source'],
                ],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($transactions->autoPagingIterator() as $bt) {
                $result['total']++;

                try {
                    $chargeId = $this->resolveChargeId($bt);
                    $payoutId = $this->resolvePayoutId($bt);
                    $chargeExtras = $this->extractChargeSourceExtras($bt);

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
                        'stripe_payment_intent_id' => $chargeExtras['stripe_payment_intent_id'],
                        'stripe_payout_id' => $payoutId,
                        'fee_details' => $this->stripeObjectToArray($bt->fee_details ?? null),
                        'source_metadata' => $chargeExtras['source_metadata'],
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

    /**
     * @return array{source_metadata: ?array<string, mixed>, stripe_payment_intent_id: ?string}
     */
    protected function extractChargeSourceExtras(object $bt): array
    {
        $out = [
            'source_metadata' => null,
            'stripe_payment_intent_id' => null,
        ];

        if (($bt->type ?? null) !== 'charge') {
            return $out;
        }

        $source = $bt->source ?? null;
        if (! is_object($source)) {
            return $out;
        }

        if (($source->object ?? null) !== 'charge') {
            return $out;
        }

        $out['source_metadata'] = $this->stripeObjectToArray($source->metadata ?? null);

        $pi = $source->payment_intent ?? null;
        if (is_string($pi) && str_starts_with($pi, 'pi_')) {
            $out['stripe_payment_intent_id'] = $pi;
        } elseif (is_object($pi) && isset($pi->id) && is_string($pi->id) && str_starts_with($pi->id, 'pi_')) {
            $out['stripe_payment_intent_id'] = $pi->id;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function stripeObjectToArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value !== [] ? $value : null;
        }

        $json = json_encode($value);
        if ($json === false || $json === '[]' || $json === '{}') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
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

    protected function resolvePayoutId(object $bt): ?string
    {
        $payout = $bt->payout ?? null;
        if (is_string($payout) && str_starts_with($payout, 'po_')) {
            return $payout;
        }
        if (is_object($payout) && isset($payout->id) && is_string($payout->id) && str_starts_with($payout->id, 'po_')) {
            return $payout->id;
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
