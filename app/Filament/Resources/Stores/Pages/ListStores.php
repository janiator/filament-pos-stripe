<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Actions\Stores\SyncStoresFromStripe;
use App\Filament\Resources\Stores\StoreResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('syncStripeAccounts')
                ->label('Sync Stripe accounts')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    app(SyncStoresFromStripe::class)(notify: true);
                }),
        ];
    }
}
