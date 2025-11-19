<?php

namespace App\Actions\Stores;

use App\Models\Store;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Throwable;

class SyncStoresFromStripe
{
    public function __invoke(bool $notify = false): array
    {
        $result = [
            'total'   => 0,
            'created' => 0,
            'updated' => 0,
            'errors'  => [],
        ];

        try {
            $secret = config('cashier.secret') ?? config('services.stripe.secret');

            if (! $secret) {
                if ($notify) {
                    Notification::make()
                        ->title('Stripe not configured')
                        ->body('No Stripe secret key found in cashier.secret or services.stripe.secret.')
                        ->danger()
                        ->send();
                }

                return $result;
            }

            $stripe = new StripeClient($secret);

            $accounts = $stripe->accounts->all(['limit' => 100]);

            foreach ($accounts->autoPagingIterator() as $account) {
                $result['total']++;

                $email =
                    $account->email
                    ?? ($account->business_profile->support_email ?? null)
                    ?? "{$account->id}@connected.test";

                // Prefer match by stripe_account_id
                $store = Store::where('stripe_account_id', $account->id)->first();

                // Fallback: match by email
                if (! $store) {
                    $store = Store::where('email', $email)->first();
                }

                $storeName = $account->business_profile->name
                    ?? $account->business_profile->url
                        ?? $account->id;

                // Generate slug for store
                $storeSlug = Str::slug($storeName);
                
                // Ensure slug is unique by appending account ID if needed
                $existingStore = Store::where('slug', $storeSlug)->first();
                if ($existingStore && $existingStore->stripe_account_id !== $account->id) {
                    $storeSlug = $storeSlug . '-' . Str::substr($account->id, -8);
                }

                $data = [
                    'name'             => $storeName,
                    'slug'             => $storeSlug,
                    'email'            => $email,
                    'stripe_account_id'=> $account->id,
                ];

                if ($store) {
                    $data['commission_type'] = $store->commission_type ?? 'percentage';
                    $data['commission_rate'] = $store->commission_rate ?? 0;

                    $store->fill($data)->save();
                    $result['updated']++;
                } else {
                    $data['commission_type'] = 'percentage';
                    $data['commission_rate'] = 0;

                    $store = Store::create($data);
                    $result['created']++;
                }

                // FIX: only upsert fields that actually exist on stripe_connect_mappings
                DB::table('stripe_connect_mappings')->updateOrInsert(
                    [
                        'model'    => Store::class,
                        'model_id' => $store->getKey(),
                    ],
                    [
                        'stripe_account_id' => $account->id,
                    ]
                );
            }

            if ($notify) {
                Notification::make()
                    ->title('Stripe sync complete')
                    ->body("Found {$result['total']} Stripe accounts. {$result['created']} created, {$result['updated']} updated.")
                    ->success()
                    ->send();
            }
        } catch (Throwable $e) {
            $result['errors'][] = $e->getMessage();

            if ($notify) {
                Notification::make()
                    ->title('Stripe sync failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }

        return $result;
    }
}
