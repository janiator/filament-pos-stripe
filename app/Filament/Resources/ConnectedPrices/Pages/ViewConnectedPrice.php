<?php

namespace App\Filament\Resources\ConnectedPrices\Pages;

use App\Filament\Resources\ConnectedPrices\ConnectedPriceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConnectedPrice extends ViewRecord
{
    protected static string $resource = ConnectedPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
