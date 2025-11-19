<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Pages;

use App\Actions\ConnectedPaymentLinks\SyncConnectedPaymentLinksFromStripe;
use App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListConnectedPaymentLinks extends ListRecords
{
    protected static string $resource = ConnectedPaymentLinkResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()
            ->with(['store', 'price']);
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
                ->modalHeading('Sync Payment Links from Stripe')
                ->modalDescription('This will sync all payment links from all connected Stripe accounts. This may take a moment.')
                ->action(function () {
                    $syncAction = new SyncConnectedPaymentLinksFromStripe();
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
                            ->body("Found {$totalFound} payment links. {$totalCreated} created, {$totalUpdated} updated.\n\nErrors:\n{$errorDetails}")
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Sync complete')
                            ->body("Found {$totalFound} payment links. {$totalCreated} created, {$totalUpdated} updated.")
                            ->success()
                            ->send();
                    }

                    $this->refresh();
                }),
        ];
    }
}
