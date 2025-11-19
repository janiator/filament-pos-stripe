<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Pages;

use App\Actions\ConnectedPaymentMethods\SyncConnectedPaymentMethodsFromStripe;
use App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListConnectedPaymentMethods extends ListRecords
{
    protected static string $resource = ConnectedPaymentMethodResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()
            ->with(['store'])
            ->with(['customer' => function ($q) {
                if (class_exists(\App\Models\ConnectedCustomer::class)) {
                    $q->whereColumn('stripe_account_id', 'connected_payment_methods.stripe_account_id');
                }
            }]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Payment methods are created via Stripe.js or Payment Intents, not manually
            // CreateAction::make(),

            Action::make('syncFromStripe')
                ->label('Sync from Stripe')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync Payment Methods from Stripe')
                ->modalDescription('This will sync all payment methods from all connected Stripe accounts. This may take a moment.')
                ->action(function () {
                    $syncAction = new SyncConnectedPaymentMethodsFromStripe();
                    $stores = Store::whereNotNull('stripe_account_id')->get();

                    $totalCreated = 0;
                    $totalUpdated = 0;
                    $totalFound = 0;
                    $errors = [];

                    foreach ($stores as $store) {
                        $result = $syncAction($store, false);
                        $totalFound += $result['total'];
                        $totalCreated += $result['created'];
                        $totalUpdated += $result['updated'];
                        $errors = array_merge($errors, $result['errors']);
                    }

                    if (! empty($errors)) {
                        $errorDetails = implode("\n", array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $errorDetails .= "\n... and " . (count($errors) - 5) . " more error(s)";
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Sync completed with errors')
                            ->body("Found {$totalFound} payment methods. {$totalCreated} created, {$totalUpdated} updated.\n\nErrors:\n{$errorDetails}")
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Sync complete')
                            ->body("Found {$totalFound} payment methods. {$totalCreated} created, {$totalUpdated} updated.")
                            ->success()
                            ->send();
                    }

                    $this->refresh();
                }),
        ];
    }
}
