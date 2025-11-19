<?php

namespace App\Filament\Resources\ConnectedPrices\Pages;

use App\Filament\Resources\ConnectedPrices\ConnectedPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedPrice extends EditRecord
{
    protected static string $resource = ConnectedPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
