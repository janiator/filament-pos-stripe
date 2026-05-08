<?php

namespace App\Filament\Resources\StoreStripeBalanceTransactions\Pages;

use App\Actions\Stripe\SyncStoreStripeBalanceTransactionsFromStripe;
use App\Filament\Resources\StoreStripeBalanceTransactions\StoreStripeBalanceTransactionResource;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStoreStripeBalanceTransactions extends ListRecords
{
    protected static string $resource = StoreStripeBalanceTransactionResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->with(['store']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromStripe')
                ->label(__('filament.resources.store_stripe_balance_transaction.actions.sync'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('filament.resources.store_stripe_balance_transaction.actions.sync_heading'))
                ->modalDescription(fn () => $this->syncModalDescription())
                ->action(function () {
                    $sync = new SyncStoreStripeBalanceTransactionsFromStripe;
                    $stores = Store::getStoresForSync();

                    $totalCreated = 0;
                    $totalUpdated = 0;
                    $totalFound = 0;
                    $errors = [];

                    foreach ($stores as $store) {
                        $result = $sync($store, false);
                        $totalFound += $result['total'];
                        $totalCreated += $result['created'];
                        $totalUpdated += $result['updated'];
                        $errors = array_merge($errors, $result['errors']);
                    }

                    if ($errors !== []) {
                        $errorDetails = implode("\n", array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $errorDetails .= "\n... and ".(count($errors) - 5).' more error(s)';
                        }

                        \Filament\Notifications\Notification::make()
                            ->title(__('filament.resources.store_stripe_balance_transaction.notifications.sync_errors_title'))
                            ->body(__('filament.resources.store_stripe_balance_transaction.notifications.sync_errors_body', [
                                'total' => $totalFound,
                                'created' => $totalCreated,
                                'updated' => $totalUpdated,
                                'errors' => $errorDetails,
                            ]))
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title(__('filament.resources.store_stripe_balance_transaction.notifications.sync_ok_title'))
                            ->body(__('filament.resources.store_stripe_balance_transaction.notifications.sync_ok_body', [
                                'total' => $totalFound,
                                'created' => $totalCreated,
                                'updated' => $totalUpdated,
                            ]))
                            ->success()
                            ->send();
                    }

                    $this->refresh();
                }),
        ];
    }

    protected function syncModalDescription(): string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                return __('filament.resources.store_stripe_balance_transaction.actions.sync_description_tenant');
            }
        } catch (\Throwable) {
        }

        return __('filament.resources.store_stripe_balance_transaction.actions.sync_description_all');
    }
}
