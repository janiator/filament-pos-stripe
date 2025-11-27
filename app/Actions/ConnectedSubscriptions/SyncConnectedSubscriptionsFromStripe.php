<?php

namespace App\Actions\ConnectedSubscriptions;

use App\Models\ConnectedSubscription;
use App\Models\ConnectedSubscriptionItem;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncConnectedSubscriptionsFromStripe
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

            // Get subscriptions from the connected account
            $subscriptions = $stripe->subscriptions->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($subscriptions->autoPagingIterator() as $subscription) {
                $result['total']++;

                try {
                    // Get the price ID from the subscription
                    $priceId = $subscription->items->data[0]->price->id ?? null;

                    // Ensure stripe_account_id is still valid
                    if (empty($stripeAccountId)) {
                        $result['errors'][] = "Subscription {$subscription->id}: stripe_account_id is empty (store: {$store->id})";
                        continue;
                    }

                    $data = [
                        'name' => $subscription->metadata->name ?? $subscription->id, // Use metadata name or subscription ID
                        'stripe_id' => $subscription->id,
                        'stripe_status' => $subscription->status,
                        'stripe_customer_id' => $subscription->customer,
                        'stripe_account_id' => $stripeAccountId, // Use refreshed value
                        'connected_price_id' => $priceId,
                        'quantity' => $subscription->items->data[0]->quantity ?? 1,
                        'trial_ends_at' => $subscription->trial_end ? date('Y-m-d H:i:s', $subscription->trial_end) : null,
                        'ends_at' => $subscription->ended_at ? date('Y-m-d H:i:s', $subscription->ended_at) : null,
                        'current_period_start' => $subscription->current_period_start ? date('Y-m-d H:i:s', $subscription->current_period_start) : null,
                        'current_period_end' => $subscription->current_period_end ? date('Y-m-d H:i:s', $subscription->current_period_end) : null,
                        'billing_cycle_anchor' => $subscription->billing_cycle_anchor ? date('Y-m-d H:i:s', $subscription->billing_cycle_anchor) : null,
                        'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false,
                        'collection_method' => $subscription->collection_method ?? null,
                        'currency' => $subscription->currency,
                        'metadata' => $subscription->metadata ? (array) $subscription->metadata : null,
                    ];

                    // Double-check stripe_account_id is not null
                    if (empty($data['stripe_account_id'])) {
                        $result['errors'][] = "Subscription {$subscription->id}: stripe_account_id is null after data preparation";
                        continue;
                    }

                    // Find by stripe_id only (since it's unique)
                    // The same subscription might exist with a different stripe_account_id
                    $subscriptionRecord = ConnectedSubscription::where('stripe_id', $subscription->id)->first();

                    if ($subscriptionRecord) {
                        // Update existing record - ensure stripe_account_id is set correctly
                        $subscriptionRecord->fill($data);
                        // Explicitly set stripe_account_id to ensure it's updated if it changed
                        $subscriptionRecord->stripe_account_id = $stripeAccountId;
                        $subscriptionRecord->save();
                        $result['updated']++;
                    } else {
                        // Create new record
                        $subscriptionRecord = ConnectedSubscription::create($data);
                        $result['created']++;
                    }

                    // Sync subscription items
                    if (isset($subscription->items->data)) {
                        foreach ($subscription->items->data as $item) {
                            ConnectedSubscriptionItem::updateOrCreate(
                                [
                                    'stripe_id' => $item->id,
                                    'connected_subscription_id' => $subscriptionRecord->id,
                                ],
                                [
                                    'connected_product' => $item->price->product ?? null,
                                    'connected_price' => $item->price->id ?? null,
                                    'quantity' => $item->quantity ?? 1,
                                ]
                            );
                        }
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Subscription {$subscription->id}: {$e->getMessage()}";
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
                        ->body("Found {$result['total']} subscriptions. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Subscriptions synced')
                        ->body("Found {$result['total']} subscriptions. {$result['created']} created, {$result['updated']} updated.")
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
