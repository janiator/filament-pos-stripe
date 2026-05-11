<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Pages;

use App\Filament\Resources\ConnectedPaymentIntents\ConnectedPaymentIntentResource;
use App\Jobs\SyncStorePaymentIntentsFromStripeJob;
use App\Models\Store;
use App\Support\Filament\QueueStripeConnectedResourceSync;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListConnectedPaymentIntents extends ListRecords
{
    protected static string $resource = ConnectedPaymentIntentResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery()
            ->with(['store']);

        if (class_exists(\App\Models\ConnectedCustomer::class)) {
            $query->with(['customer']);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromStripe')
                ->label('Sync from Stripe')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync Payment Intents from Stripe')
                ->modalDescription(fn () => $this->getSyncDescription('payment intents'))
                ->action(function () {
                    QueueStripeConnectedResourceSync::dispatch(
                        'Sync payment intents from Stripe',
                        'payment intents',
                        fn (Store $store): SyncStorePaymentIntentsFromStripeJob => new SyncStorePaymentIntentsFromStripeJob($store),
                    );

                    $this->refresh();
                }),
        ];
    }

    protected function getSyncDescription(string $type): string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                return "This will sync all {$type} from the current store's Stripe account. The sync runs in the background and may take several minutes.";
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        return "This will sync all {$type} from all connected Stripe accounts. Jobs run in the background and may take several minutes.";
    }
}
