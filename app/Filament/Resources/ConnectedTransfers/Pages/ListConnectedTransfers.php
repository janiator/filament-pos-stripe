<?php

namespace App\Filament\Resources\ConnectedTransfers\Pages;

use App\Actions\ConnectedTransfers\SyncConnectedTransfersFromStripe;
use App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource;
use App\Models\Store;
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
                    $syncAction = new SyncConnectedTransfersFromStripe();
                    $stores = Store::getStoresForSync();

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
                            ->body("Found {$totalFound} transfers. {$totalCreated} created, {$totalUpdated} updated.\n\nErrors:\n{$errorDetails}")
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Sync complete')
                            ->body("Found {$totalFound} transfers. {$totalCreated} created, {$totalUpdated} updated.")
                            ->success()
                            ->send();
                    }

                    $this->refresh();
                }),
        ];
    }

    protected function getSyncDescription(string $type): string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                return "This will sync all {$type} from the current team's Stripe account. This may take a moment.";
            }
        } catch (\Throwable $e) {
            // Fallback
        }
        return "This will sync all {$type} from all connected Stripe accounts. This may take a moment.";
    }
}
