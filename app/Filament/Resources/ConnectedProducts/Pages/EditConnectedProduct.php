<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedProduct extends EditRecord
{
    protected static string $resource = ConnectedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
