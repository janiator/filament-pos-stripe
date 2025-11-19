<?php

namespace App\Actions;

use App\Actions\ConnectedCharges\SyncConnectedChargesFromStripe;
use App\Actions\ConnectedTransfers\SyncConnectedTransfersFromStripe;
use App\Actions\ConnectedPaymentMethods\SyncConnectedPaymentMethodsFromStripe;
use App\Actions\ConnectedPaymentLinks\SyncConnectedPaymentLinksFromStripe;
use App\Models\Store;
use Filament\Notifications\Notification;

class SyncEverythingFromStripe
{
    public function __invoke(bool $notify = false): array
    {
        $stores = Store::whereNotNull('stripe_account_id')->get();

        if ($stores->isEmpty()) {
            if ($notify) {
                Notification::make()
                    ->title('No stores found')
                    ->body('No stores with connected Stripe accounts found.')
                    ->warning()
                    ->send();
            }
            return [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => [],
            ];
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalFound = 0;
        $allErrors = [];

        // Sync charges
        $chargeSync = new SyncConnectedChargesFromStripe();
        foreach ($stores as $store) {
            $result = $chargeSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync transfers
        $transferSync = new SyncConnectedTransfersFromStripe();
        foreach ($stores as $store) {
            $result = $transferSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync payment methods
        $paymentMethodSync = new SyncConnectedPaymentMethodsFromStripe();
        foreach ($stores as $store) {
            $result = $paymentMethodSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
        }

        // Sync payment links
        $paymentLinkSync = new SyncConnectedPaymentLinksFromStripe();
        foreach ($stores as $store) {
            $result = $paymentLinkSync($store, false);
            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);
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

