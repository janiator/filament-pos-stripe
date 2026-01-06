<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Actions\Stores\SyncStoreTerminalLocationsFromStripe;
use App\Actions\Stores\SyncStoreTerminalReadersFromStripe;
use App\Filament\Resources\Stores\StoreResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewStore extends ViewRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('syncEverything')
                ->label('Sync Everything from Stripe')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Sync Everything from Stripe')
                ->modalDescription('This will sync all data (customers, products, subscriptions, charges, transfers, payment methods, payment links, and terminal devices) from Stripe for this store. The sync will run in the background.')
                ->action(function () {
                    $store = $this->record;
                    
                    // Dispatch job instead of running synchronously
                    \App\Jobs\SyncStoreEverythingFromStripeJob::dispatch($store);

                    Notification::make()
                        ->title('Sync queued')
                        ->body('The sync has been queued and will run in the background. You can check the progress in the queue dashboard.')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => !empty($this->record->stripe_account_id)),

            Action::make('syncStripeTerminal')
                ->label('Sync Stripe Terminal')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    $store = $this->record;

                    $locResult    = app(SyncStoreTerminalLocationsFromStripe::class)($store);
                    $readerResult = app(SyncStoreTerminalReadersFromStripe::class)($store);

                    if ($locResult['error'] || $readerResult['error']) {
                        $error = $locResult['error'] ?? $readerResult['error'];

                        Notification::make()
                            ->title('Stripe Terminal sync failed')
                            ->body($error)
                            ->danger()
                            ->send();

                        return;
                    }

                    $message =
                        "Locations: {$locResult['total']} found, {$locResult['created']} created, {$locResult['updated']} updated. " .
                        "Readers: {$readerResult['total']} found, {$readerResult['created']} created, {$readerResult['updated']} updated.";

                    Notification::make()
                        ->title('Stripe Terminal sync complete')
                        ->body($message)
                        ->success()
                        ->send();

                    // Optional, but safe if you want to re-query the model:
                    // $this->record->refresh();
                }),
        ];
    }
}
