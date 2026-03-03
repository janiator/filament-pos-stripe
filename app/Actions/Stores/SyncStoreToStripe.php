<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Throwable;

class SyncStoreToStripe
{
    public function __invoke(Store $store, ?StripeClient $stripe = null): void
    {
        if ($stripe === null) {
            $secret = config('cashier.secret') ?? config('services.stripe.secret');
            if (! $secret) {
                return;
            }
            $stripe = new StripeClient($secret);
        }

        try {
            // If the Store has no connected account yet, create one
            if (! $store->stripe_account_id) {
                // You could also call: $store->createAsStripeAccount('standard', $data);
                $account = $stripe->accounts->create([
                    'type' => 'standard', // or 'express' / 'custom' as needed
                    'email' => $store->email,
                    'business_profile' => [
                        'name' => $store->name,
                    ],
                ]);

                $store->stripe_account_id = $account->id;
                $store->save();
            } else {
                // Update existing Stripe account
                // Note: email cannot be updated via API for connected accounts
                // Only business_profile.name can be updated
                $updateData = [
                    'business_profile' => [
                        'name' => $store->name,
                    ],
                ];

                $stripe->accounts->update($store->stripe_account_id, $updateData);
            }
        } catch (\Stripe\Exception\PermissionException $e) {
            // Some fields (like email) cannot be updated via API for connected accounts
            // Log but don't throw - allow the model save to complete
            \Log::warning('SyncStoreToStripe: Permission denied updating Stripe account', [
                'store_id' => $store->id,
                'stripe_account_id' => $store->stripe_account_id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        } catch (InvalidRequestException $e) {
            if (str_contains($e->getMessage(), 'Only live keys can access this method')) {
                \Log::warning('SyncStoreToStripe: Skipping account update (Stripe test keys cannot update connected accounts)', [
                    'store_id' => $store->id,
                    'stripe_account_id' => $store->stripe_account_id,
                ]);

                return;
            }
            \Log::error('SyncStoreToStripe: Failed to sync store to Stripe', [
                'store_id' => $store->id,
                'stripe_account_id' => $store->stripe_account_id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        } catch (Throwable $e) {
            // Log other errors but don't throw - allow the model save to complete
            \Log::error('SyncStoreToStripe: Failed to sync store to Stripe', [
                'store_id' => $store->id,
                'stripe_account_id' => $store->stripe_account_id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}
