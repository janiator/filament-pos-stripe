<?php

namespace App\Filament\Resources\ConnectedTransfers\Pages;

use App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource;
use App\Jobs\SyncStoreTransfersFromStripeJob;
use App\Models\Store;
use App\Support\Filament\QueueStripeConnectedResourceSync;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListConnectedTransfers extends ListRecords
{
    protected static string $resource = ConnectedTransferResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()
            ->with(['store', 'charge']);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('syncFromStripe')
                ->label('Sync from Stripe')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync Transfers from Stripe')
                ->modalDescription(fn () => $this->getSyncDescription('transfers'))
                ->action(function () {
                    QueueStripeConnectedResourceSync::dispatch(
                        'Sync transfers from Stripe',
                        'transfers',
                        fn (Store $store): SyncStoreTransfersFromStripeJob => new SyncStoreTransfersFromStripeJob($store),
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
                return "This will sync all {$type} from the current team's Stripe account. The sync runs in the background and may take several minutes.";
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        return "This will sync all {$type} from all connected Stripe accounts. Jobs run in the background and may take several minutes.";
    }
}
