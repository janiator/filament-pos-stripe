<?php

namespace App\Filament\Resources\ConnectedPrices\Pages;

use App\Filament\Resources\ConnectedPrices\ConnectedPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnectedPrices extends ListRecords
{
    protected static string $resource = ConnectedPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
