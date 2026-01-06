<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedSubscription;
use App\Models\ConnectedSubscriptionItem;
use App\Models\Store;
use Stripe\Subscription;

class HandleSubscriptionWebhook
{
    public function handle(Subscription $subscription, string $eventType, ?string $accountId = null): void
    {
        if (!$accountId) {
            \Log::warning('Subscription webhook received but no account ID provided', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            // Log available stores for debugging
            $availableStores = Store::whereNotNull('stripe_account_id')
                ->pluck('stripe_account_id', 'id')
                ->toArray();
            
            \Log::warning('Subscription webhook received but store not found', [
                'subscription_id' => $subscription->id,
                'account_id' => $accountId,
                'available_stores' => array_keys($availableStores),
                'available_account_ids' => array_values($availableStores),
            ]);
            return;
        }
        
        \Log::info('Processing subscription webhook', [
            'subscription_id' => $subscription->id,
            'account_id' => $accountId,
            'store_id' => $store->id,
            'event_type' => $eventType,
        ]);

        // Get the price ID from the subscription
        $priceId = $subscription->items->data[0]->price->id ?? null;
        
        // Sync the subscription
        $data = [
            'name' => ($subscription->metadata && isset($subscription->metadata->name)) ? $subscription->metadata->name : $subscription->id, // Use metadata name or subscription ID
            'stripe_id' => $subscription->id,
            'stripe_status' => $subscription->status,
            'stripe_customer_id' => $subscription->customer,
            'stripe_account_id' => $store->stripe_account_id,
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

        // Use withoutEvents to prevent triggering sync back to Stripe when syncing FROM Stripe
        $subscriptionRecord = ConnectedSubscription::withoutEvents(function () use ($subscription, $store, $data) {
            return ConnectedSubscription::updateOrCreate(
                [
                    'stripe_id' => $subscription->id,
                    'stripe_account_id' => $store->stripe_account_id,
                ],
                $data
            );
        });
        
        \Log::info('Subscription webhook processed successfully', [
            'subscription_id' => $subscription->id,
            'subscription_record_id' => $subscriptionRecord->id,
            'was_created' => $subscriptionRecord->wasRecentlyCreated,
        ]);

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
    }
}

