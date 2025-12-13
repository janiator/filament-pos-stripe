<?php

namespace App\Actions;

use App\Actions\ConnectedCharges\SyncConnectedChargesFromStripe;
use App\Actions\ConnectedCustomers\SyncConnectedCustomersFromStripe;
use App\Actions\ConnectedPaymentIntents\SyncConnectedPaymentIntentsFromStripe;
use App\Actions\ConnectedPaymentLinks\SyncConnectedPaymentLinksFromStripe;
use App\Actions\ConnectedPaymentMethods\SyncConnectedPaymentMethodsFromStripe;
use App\Actions\ConnectedProducts\SyncConnectedProductsFromStripe;
use App\Actions\ConnectedSubscriptions\SyncConnectedSubscriptionsFromStripe;
use App\Actions\ConnectedTransfers\SyncConnectedTransfersFromStripe;
use App\Actions\Stores\SyncStoresFromStripe;
use App\Actions\Stores\SyncStoreTerminalLocationsFromStripe;
use App\Actions\Stores\SyncStoreTerminalReadersFromStripe;
use App\Models\Store;
use Filament\Notifications\Notification;

class SyncEverythingFromStripe
{
    public function __invoke(bool $notify = false): array
    {
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalFound = 0;
        $allErrors = [];

        // Sync stores first (this ensures we have the latest store data before syncing everything else)
        // This is important because new stores might exist in Stripe that haven't been created locally yet
        $storeSync = new SyncStoresFromStripe();
        $storeResult = $storeSync(false);
        $totalFound += $storeResult['total'];
        $totalCreated += $storeResult['created'];
        $totalUpdated += $storeResult['updated'];
        $allErrors = array_merge($allErrors, $storeResult['errors']);

        // Refresh the stores list after syncing to get any newly created stores
        $stores = Store::getStoresForSync();

        if ($stores->isEmpty()) {
            if ($notify) {
                Notification::make()
                    ->title('No stores found')
                    ->body('No stores with connected Stripe accounts found after syncing.')
                    ->warning()
                    ->send();
            }
            return [
                'total' => $totalFound,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'errors' => $allErrors,
            ];
        }

        // Sync customers
        $customerSync = new SyncConnectedCustomersFromStripe();
        foreach ($stores as $store) {
            // Refresh the store to ensure we have the latest data
            $store->refresh();
            // Skip if store doesn't have stripe_account_id
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $customerSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync products (also syncs prices and variants)
        // Variants are synced as separate Stripe products but stored in ProductVariant table
        // The sync handles variants in two passes: first pass syncs what it can,
        // second pass retries variants whose parent products weren't found yet
        $productSync = new SyncConnectedProductsFromStripe();
        foreach ($stores as $store) {
            $store->refresh();
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $productSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync subscriptions
        $subscriptionSync = new SyncConnectedSubscriptionsFromStripe();
        foreach ($stores as $store) {
            $store->refresh();
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $subscriptionSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync payment intents
        $paymentIntentSync = new SyncConnectedPaymentIntentsFromStripe();
        foreach ($stores as $store) {
            $store->refresh();
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $paymentIntentSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync charges
        $chargeSync = new SyncConnectedChargesFromStripe();
        foreach ($stores as $store) {
            $store->refresh();
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $chargeSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync transfers
        $transferSync = new SyncConnectedTransfersFromStripe();
        foreach ($stores as $store) {
            $store->refresh();
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $transferSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync payment methods
        $paymentMethodSync = new SyncConnectedPaymentMethodsFromStripe();
        foreach ($stores as $store) {
            $store->refresh();
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $paymentMethodSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync payment links
        $paymentLinkSync = new SyncConnectedPaymentLinksFromStripe();
        foreach ($stores as $store) {
            $store->refresh();
            if (!$store->stripe_account_id) {
                continue;
            }
            $result = $paymentLinkSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync terminal locations
        $terminalLocationSync = new SyncStoreTerminalLocationsFromStripe();
        foreach ($stores as $store) {
            $result = $terminalLocationSync($store);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            if ($result['error']) {
                $allErrors[] = "Terminal locations: {$result['error']}";
            }
        }

        // Sync terminal readers
        $terminalReaderSync = new SyncStoreTerminalReadersFromStripe();
        foreach ($stores as $store) {
            $result = $terminalReaderSync($store);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            if ($result['error']) {
                $allErrors[] = "Terminal readers: {$result['error']}";
            }
        }

        if ($notify) {
            if (! empty($allErrors)) {
                $errorDetails = implode("\n", array_slice($allErrors, 0, 5));
                if (count($allErrors) > 5) {
                    $errorDetails .= "\n... and " . (count($allErrors) - 5) . " more error(s)";
                }

                Notification::make()
                    ->title('Sync completed with errors')
                    ->body("Found {$totalFound} items. {$totalCreated} created, {$totalUpdated} updated.\n\nErrors:\n{$errorDetails}")
                    ->warning()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title('Sync complete')
                    ->body("Found {$totalFound} items. {$totalCreated} created, {$totalUpdated} updated.")
                    ->success()
                    ->send();
            }
        }

        return [
            'total' => $totalFound,
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'errors' => $allErrors,
        ];
    }

    /**
     * Sync everything from Stripe for a single store
     */
    public function syncStore(Store $store, bool $notify = false): array
    {
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalFound = 0;
        $allErrors = [];

        // Refresh the store to ensure we have the latest data
        $store->refresh();

        // Skip if store doesn't have stripe_account_id
        if (!$store->stripe_account_id) {
            if ($notify) {
                Notification::make()
                    ->title('Store not connected to Stripe')
                    ->body('This store does not have a Stripe account ID.')
                    ->warning()
                    ->send();
            }
            return [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => ['Store does not have a Stripe account ID'],
            ];
        }

        // Sync customers
        $customerSync = new SyncConnectedCustomersFromStripe();
        $result = $customerSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync products (also syncs prices and variants)
        $productSync = new SyncConnectedProductsFromStripe();
        $result = $productSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync subscriptions
        $subscriptionSync = new SyncConnectedSubscriptionsFromStripe();
        $result = $subscriptionSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync payment intents
        $paymentIntentSync = new SyncConnectedPaymentIntentsFromStripe();
        $result = $paymentIntentSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync charges
        $chargeSync = new SyncConnectedChargesFromStripe();
        $result = $chargeSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync transfers
        $transferSync = new SyncConnectedTransfersFromStripe();
        $result = $transferSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync payment methods
        $paymentMethodSync = new SyncConnectedPaymentMethodsFromStripe();
        $result = $paymentMethodSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync payment links
        $paymentLinkSync = new SyncConnectedPaymentLinksFromStripe();
        $result = $paymentLinkSync($store, false);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        $allErrors = array_merge($allErrors, $result['errors']);

        // Sync terminal locations
        $terminalLocationSync = new SyncStoreTerminalLocationsFromStripe();
        $result = $terminalLocationSync($store);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        if ($result['error']) {
            $allErrors[] = "Terminal locations: {$result['error']}";
        }

        // Sync terminal readers
        $terminalReaderSync = new SyncStoreTerminalReadersFromStripe();
        $result = $terminalReaderSync($store);
        $totalFound += $result['total'];
        $totalCreated += $result['created'];
        $totalUpdated += $result['updated'];
        if ($result['error']) {
            $allErrors[] = "Terminal readers: {$result['error']}";
        }

        if ($notify) {
            if (! empty($allErrors)) {
                $errorDetails = implode("\n", array_slice($allErrors, 0, 5));
                if (count($allErrors) > 5) {
                    $errorDetails .= "\n... and " . (count($allErrors) - 5) . " more error(s)";
                }

                Notification::make()
                    ->title('Sync completed with errors')
                    ->body("Found {$totalFound} items. {$totalCreated} created, {$totalUpdated} updated.\n\nErrors:\n{$errorDetails}")
                    ->warning()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()
                    ->title('Sync complete')
                    ->body("Found {$totalFound} items. {$totalCreated} created, {$totalUpdated} updated.")
                    ->success()
                    ->send();
            }
        }

        return [
            'total' => $totalFound,
            'created' => $totalCreated,
            'updated' => $totalUpdated,
            'errors' => $allErrors,
        ];
    }
}

