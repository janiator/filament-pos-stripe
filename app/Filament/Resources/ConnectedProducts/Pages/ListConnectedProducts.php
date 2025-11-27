<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnectedProducts extends ListRecords
{
    protected static string $resource = ConnectedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import from Shopify CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(ConnectedProductResource::getUrl('import-shopify-csv')),
            CreateAction::make(),
        ];
    }
}
