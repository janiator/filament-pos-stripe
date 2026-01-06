<?php

namespace App\Actions\Webhooks;

use App\Models\ConnectedCustomer;
use App\Models\Store;
use Stripe\Customer;

class HandleCustomerWebhook
{
    public function handle(Customer $customer, string $eventType, ?string $accountId = null): void
    {
        // For Connect webhooks, we need to find the store by account_id
        if (!$accountId) {
            \Log::warning('Customer webhook received but no account ID provided', [
                'customer_id' => $customer->id,
            ]);
            return;
        }
        
        $store = Store::where('stripe_account_id', $accountId)->first();
        
        if (!$store) {
            \Log::warning('Customer webhook received but store not found', [
                'customer_id' => $customer->id,
                'account_id' => $accountId,
            ]);
            return;
        }

        // Sync the specific customer
        $data = [
            'stripe_customer_id' => $customer->id,
            'stripe_account_id' => $store->stripe_account_id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone ?? null,
            'address' => $customer->address ? (array) $customer->address : null,
        ];

        // Use withoutEvents to prevent triggering sync back to Stripe when syncing FROM Stripe
        ConnectedCustomer::withoutEvents(function () use ($customer, $store, $data) {
            return ConnectedCustomer::updateOrCreate(
                [
                    'stripe_customer_id' => $customer->id,
                    'stripe_account_id' => $store->stripe_account_id,
                ],
                $data
            );
        });
    }
}

