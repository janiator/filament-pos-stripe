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
            // Refresh store FIRST to get the latest stripe_account_id
            $store->refresh();
            $stripeAccountId = $store->stripe_account_id;
            
            // Final validation - if still null or empty, skip this store
            if (empty($stripeAccountId)) {
                $result['errors'][] = "Store '{$store->name}' (ID: {$store->id}) does not have a stripe_account_id";
                \Log::warning('Store missing stripe_account_id', [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                ]);
                
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
            
            // Log for debugging
            \Log::debug('Syncing customers for store', [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'stripe_account_id' => $stripeAccountId,
            ]);

            // Get customers from the connected account
            // According to Stripe API docs, the second parameter should be request options
            // with 'stripe_account' key for connected accounts
            $customers = $stripe->customers->all(
                ['limit' => 100],
                ['stripe_account' => $stripeAccountId]
            );

            foreach ($customers->autoPagingIterator() as $customer) {
                $result['total']++;

                try {
                    // Double-check stripe_account_id is still set
                    if (empty($stripeAccountId)) {
                        $result['errors'][] = "Customer {$customer->id}: stripe_account_id is empty (store: {$store->id})";
                        \Log::error('stripe_account_id became empty during sync', [
                            'customer_id' => $customer->id,
                            'store_id' => $store->id,
                        ]);
                        continue;
                    }
                    
                    // Find existing customer by BOTH stripe_customer_id AND stripe_account_id
                    // This ensures we're working with the correct customer for this account
                    $customerRecord = ConnectedCustomer::where('stripe_customer_id', $customer->id)
                        ->where('stripe_account_id', $stripeAccountId)
                        ->first();
                    
                    if ($customerRecord) {
                        // Update existing record
                        $customerRecord->name = $customer->name;
                        $customerRecord->email = $customer->email;
                        $customerRecord->phone = $customer->phone ?? null;
                        $customerRecord->address = $customer->address ? (array) $customer->address : null;
                        // Explicitly set stripe_account_id again to be safe
                        $customerRecord->stripe_account_id = $stripeAccountId;
                        
                        // Log before save for debugging
                        \Log::debug('Updating customer', [
                            'customer_id' => $customer->id,
                            'stripe_account_id' => $stripeAccountId,
                            'record_id' => $customerRecord->id,
                            'attributes' => $customerRecord->getAttributes(),
                        ]);
                        
                        // Use saveQuietly to prevent triggering sync back to Stripe
                        $customerRecord->saveQuietly();
                        $result['updated']++;
                    } else {
                        // Check if customer exists with same stripe_customer_id but different/null stripe_account_id
                        // This can happen if data was imported incorrectly
                        $existingCustomer = ConnectedCustomer::where('stripe_customer_id', $customer->id)
                            ->where(function ($query) use ($stripeAccountId) { $query->whereNull('stripe_account_id')
                            ->orWhere('stripe_account_id', '!=', $stripeAccountId); })
                            ->first();
                        
                        if ($existingCustomer) {
                            // Update the existing record to set the correct stripe_account_id
                            \Log::info('Found customer with different stripe_account_id, updating', [
                                'customer_id' => $customer->id,
                                'old_stripe_account_id' => $existingCustomer->stripe_account_id,
                                'new_stripe_account_id' => $stripeAccountId,
                            ]);
                            
                            $existingCustomer->stripe_account_id = $stripeAccountId;
                            $existingCustomer->name = $customer->name;
                            $existingCustomer->email = $customer->email;
                            $existingCustomer->phone = $customer->phone ?? null;
                            $existingCustomer->address = $customer->address ? (array) $customer->address : null;
                            // Use saveQuietly to prevent triggering sync back to Stripe
                            $existingCustomer->saveQuietly();
                            $result['updated']++;
                            $customerRecord = $existingCustomer;
                        } else {
                            // Create new record with all required fields
                            \Log::debug('Creating new customer', [
                                'customer_id' => $customer->id,
                                'stripe_account_id' => $stripeAccountId,
                            ]);
                            
                            // Create without triggering events
                            $customerRecord = ConnectedCustomer::withoutEvents(function () use ($customer, $stripeAccountId) {
                                return ConnectedCustomer::create([
                                    'stripe_customer_id' => $customer->id,
                                    'stripe_account_id' => $stripeAccountId,
                                    'name' => $customer->name,
                                    'email' => $customer->email,
                                    'phone' => $customer->phone ?? null,
                                    'address' => $customer->address ? (array) $customer->address : null,
                                ]);
                            });
                            $result['created']++;
                        }
                    }
                    
                    // Verify the record was saved correctly
                    $customerRecord->refresh();
                    if (empty($customerRecord->stripe_account_id)) {
                        $result['errors'][] = "Customer {$customer->id}: stripe_account_id was not saved correctly";
                        \Log::error('Customer saved without stripe_account_id', [
                            'customer_id' => $customer->id,
                            'record_id' => $customerRecord->id,
                            'stripe_account_id' => $customerRecord->stripe_account_id,
                            'store_id' => $store->id,
                            'store_stripe_account_id' => $stripeAccountId,
                        ]);
                    } else {
                        \Log::debug('Customer saved successfully', [
                            'customer_id' => $customer->id,
                            'stripe_account_id' => $customerRecord->stripe_account_id,
                        ]);
                    }
                } catch (Throwable $e) {
                    $result['errors'][] = "Customer {$customer->id}: {$e->getMessage()}";
                    \Log::error('Error syncing customer', [
                        'customer_id' => $customer->id,
                        'stripe_account_id' => $stripeAccountId,
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
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

            \Log::error('Fatal error in SyncConnectedCustomersFromStripe', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            report($e);
            return $result;
        }
    }
}
