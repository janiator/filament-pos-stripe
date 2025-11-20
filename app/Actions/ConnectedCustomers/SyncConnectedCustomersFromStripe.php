<?php

namespace App\Actions\ConnectedCustomers;

use App\Models\ConnectedCustomer;
use App\Models\Store;
use Filament\Notifications\Notification;
use Lanos\CashierConnect\Exceptions\AccountNotFoundException;
use Stripe\StripeClient;
use Throwable;

class SyncConnectedCustomersFromStripe
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
            // Ensure store has a stripe_account_id
            if (! $store->stripe_account_id) {
                if ($notify) {
                    Notification::make()
                        ->title('Store not connected')
                        ->body("Store '{$store->name}' does not have a Stripe account ID.")
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
            
            // Double-check and get the stripe_account_id - refresh if needed
            $store->refresh();
            $stripeAccountId = $store->getAttribute('stripe_account_id');
            
            // Final validation - if still null or empty, skip this store
            if (empty($stripeAccountId)) {
                $result['errors'][] = "Store '{$store->name}' (ID: {$store->id}) does not have a stripe_account_id";
                \Log::warning('Store missing stripe_account_id', [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'stripe_account_id' => $store->stripe_account_id,
                ]);
                return $result;
            }
            
            // Log for debugging
            \Log::debug('Syncing customers for store', [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'stripe_account_id' => $stripeAccountId,
            ]);

            // Get customers from the connected account
            $customers = $stripe->customers->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($customers->autoPagingIterator() as $customer) {
                $result['total']++;

                try {
                    // Ensure stripe_account_id is still set before creating
                    if (empty($stripeAccountId)) {
                        $result['errors'][] = "Customer {$customer->id}: stripe_account_id is empty (store: {$store->id})";
                        continue;
                    }
                    
                    // Use firstOrNew to get or create the record
                    $customerRecord = ConnectedCustomer::firstOrNew([
                        'stripe_customer_id' => $customer->id,
                        'stripe_account_id' => $stripeAccountId,
                    ]);
                    
                    // Set/update the attributes
                    $customerRecord->name = $customer->name;
                    $customerRecord->email = $customer->email;
                    
                    // Ensure stripe_account_id is set (in case firstOrNew didn't set it)
                    if (empty($customerRecord->stripe_account_id)) {
                        $customerRecord->stripe_account_id = $stripeAccountId;
                    }
                    
                    // Log before save
                    \Log::debug('Saving customer', [
                        'customer_id' => $customer->id,
                        'stripe_account_id' => $customerRecord->stripe_account_id,
                        'is_new' => $customerRecord->exists === false,
                        'attributes' => $customerRecord->getAttributes(),
                    ]);
                    
                    $wasNew = !$customerRecord->exists;
                    $customerRecord->save();
                    
                    if ($wasNew) {
                        $result['created']++;
                    } else {
                        $result['updated']++;
                    }
                    
                    // Verify the record was saved correctly
                    $customerRecord->refresh();
                    if (empty($customerRecord->stripe_account_id)) {
                        $result['errors'][] = "Customer {$customer->id}: stripe_account_id was not saved correctly";
                        \Log::error('Customer saved without stripe_account_id', [
                            'customer_id' => $customer->id,
                            'record_id' => $customerRecord->id,
                            'stripe_account_id' => $customerRecord->stripe_account_id,
                        ]);
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Customer {$customer->id}: {$e->getMessage()}";
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
                        ->body("Found {$result['total']} customers. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Customers synced')
                        ->body("Found {$result['total']} customers. {$result['created']} created, {$result['updated']} updated.")
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

