<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Actions\Stores\SyncStoreTerminalLocationsFromStripe;
use App\Actions\Stores\SyncStoreTerminalReadersFromStripe;
use App\Actions\SyncEverythingFromStripe;
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
                ->modalDescription('This will sync all data (customers, products, subscriptions, charges, transfers, payment methods, payment links, and terminal devices) from Stripe for this store. This may take a while.')
                ->action(function () {
                    $store = $this->record;
                    $syncAction = new SyncEverythingFromStripe();
                    $result = $syncAction->syncStore($store, false);

                    if (! empty($result['errors'])) {
                        $errorDetails = implode("\n", array_slice($result['errors'], 0, 5));
                        if (count($result['errors']) > 5) {
                            $errorDetails .= "\n... and " . (count($result['errors']) - 5) . " more error(s)";
                        }

                        Notification::make()
                            ->title('Sync completed with errors')
                            ->body("Found {$result['total']} items. {$result['created']} created, {$result['updated']} updated.\n\nErrors:\n{$errorDetails}")
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Sync complete')
                            ->body("Found {$result['total']} items. {$result['created']} created, {$result['updated']} updated.")
                            ->success()
                            ->send();
                    }
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
