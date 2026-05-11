<?php

namespace App\Actions\Stripe;

use App\Models\Store;
use App\Models\StoreStripePayout;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\Payout as StripePayout;
use Stripe\StripeClient;
use Throwable;

class SyncStoreStripePayoutsFromStripe
{
    /**
     * Upsert one payout row from a Stripe Payout object (e.g. Connect webhook payload).
     *
     * @return array{created: bool, updated: bool}
     */
    public function upsertSinglePayout(Store $store, StripePayout $payout): array
    {
        $store->refresh();
        $stripeAccountId = $store->stripe_account_id;

        if (empty($stripeAccountId) || ! $store->hasStripeAccount()) {
            return ['created' => false, 'updated' => false];
        }

        $arrivalDate = null;
        if (! empty($payout->arrival_date)) {
            $arrivalDate = \Carbon\Carbon::createFromTimestamp((int) $payout->arrival_date);
        }

        $data = [
            'store_id' => $store->id,
            'stripe_account_id' => $stripeAccountId,
            'stripe_payout_id' => $payout->id,
            'amount' => (int) $payout->amount,
            'currency' => (string) $payout->currency,
            'status' => (string) $payout->status,
            'arrival_date' => $arrivalDate,
            'method' => $payout->method ?? null,
            'failure_code' => $payout->failure_code ?? null,
            'failure_message' => $payout->failure_message ?? null,
            'statement_descriptor' => $payout->statement_descriptor ?? null,
            'automatic' => (bool) ($payout->automatic ?? true),
            'stripe_created' => (int) $payout->created,
            'metadata' => $payout->metadata ? (array) $payout->metadata : null,
        ];

        $record = StoreStripePayout::query()
            ->where('stripe_payout_id', $payout->id)
            ->first();

        if ($record) {
            $record->fill($data);
            $record->save();

            return ['created' => false, 'updated' => true];
        }

        StoreStripePayout::query()->create($data);

        return ['created' => true, 'updated' => false];
    }

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

            $payouts = $stripe->payouts->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($payouts->autoPagingIterator() as $payout) {
                $result['total']++;

                try {
                    $outcome = $this->upsertSinglePayout($store, $payout);
                    if ($outcome['created']) {
                        $result['created']++;
                    }
                    if ($outcome['updated']) {
                        $result['updated']++;
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Payout {$payout->id}: {$e->getMessage()}";
                    report($e);
                }
            }

            if ($notify) {
                $this->sendResultNotification($result, 'payouts');
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
     * @param  array{total: int, created: int, updated: int, errors: list<string>}  $result
     */
    protected function sendResultNotification(array $result, string $label): void
    {
        if ($result['errors'] !== []) {
            $errorDetails = implode("\n", array_slice($result['errors'], 0, 5));
            if (count($result['errors']) > 5) {
                $errorDetails .= "\n... and ".(count($result['errors']) - 5).' more error(s)';
            }
            Notification::make()
                ->title('Sync completed with errors')
                ->body("Found {$result['total']} {$label}. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Payouts synced')
            ->body("Found {$result['total']} {$label}. {$result['created']} created, {$result['updated']} updated.")
            ->success()
            ->send();
    }
}
