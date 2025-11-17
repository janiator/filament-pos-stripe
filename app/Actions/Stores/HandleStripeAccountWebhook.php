<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Stripe\Account;

class HandleStripeAccountWebhook
{
    /**
     * Handle account.created / account.updated
     */
    public function __invoke(Account $account): void
    {
        // Resolve an email; fall back to placeholder if not provided
        $email = $account->email
            ?? ($account->business_profile->support_email ?? null)
            ?? "{$account->id}@connected.test";

        // Prefer matching by stripe_account_id
        $store = Store::where('stripe_account_id', $account->id)->first();

        // Fallback: match by email if not already linked
        if (! $store) {
            $store = Store::where('email', $email)->first();
        }

        $data = [
            'name' => $account->business_profile->name
                ?? $account->business_profile->url
                    ?? $account->id,
            'email'             => $email,
            'stripe_account_id' => $account->id,
        ];

        if ($store) {
            // Keep existing commission config if it exists
            $data['commission_type'] = $store->commission_type ?? 'percentage';
            $data['commission_rate'] = $store->commission_rate ?? 0;

            $store->fill($data)->save();
        } else {
            // Defaults for new stores
            $data['commission_type'] = 'percentage';
            $data['commission_rate'] = 0;

            Store::create($data);
        }
    }

    /**
     * Handle account.deleted
     */
    public function handleDeleted(Account $account): void
    {
        $store = Store::where('stripe_account_id', $account->id)->first();

        if (! $store) {
            return;
        }

        // Option 1: just disconnect from Stripe
        $store->stripe_account_id = null;
        $store->save();

        // Option 2 (if you prefer): soft-delete the store instead
        // $store->delete();
    }
}
