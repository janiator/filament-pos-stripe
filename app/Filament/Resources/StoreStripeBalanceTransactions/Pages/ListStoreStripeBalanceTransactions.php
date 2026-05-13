<?php

namespace App\Filament\Resources\StoreStripeBalanceTransactions\Pages;

use App\Filament\Resources\StoreStripeBalanceTransactions\StoreStripeBalanceTransactionResource;
use App\Jobs\SyncStoreStripeBalanceTransactionsJob;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Bus;

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
                ->action(function (): void {
                    $stores = Store::getStoresForSync()->filter(fn (Store $store): bool => filled($store->stripe_account_id));

                    if ($stores->isEmpty()) {
                        Notification::make()
                            ->title(__('filament.resources.store_stripe_balance_transaction.notifications.sync_no_stores_title'))
                            ->body(__('filament.resources.store_stripe_balance_transaction.notifications.sync_no_stores_body'))
                            ->warning()
                            ->send();

                        return;
                    }

                    $jobs = $stores->map(fn (Store $store): SyncStoreStripeBalanceTransactionsJob => new SyncStoreStripeBalanceTransactionsJob($store))->all();

                    $batch = Bus::batch($jobs)
                        ->name(__('filament.resources.store_stripe_balance_transaction.notifications.sync_batch_name'))
                        ->allowFailures()
                        ->dispatch();

                    Notification::make()
                        ->title(__('filament.resources.store_stripe_balance_transaction.notifications.sync_queued_title'))
                        ->body(__('filament.resources.store_stripe_balance_transaction.notifications.sync_queued_body', [
                            'count' => count($jobs),
                            'batch' => $batch->id,
                        ]))
                        ->success()
                        ->send();

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
