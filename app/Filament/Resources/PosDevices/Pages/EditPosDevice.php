<?php

namespace App\Filament\Resources\PosDevices\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\PosDevices\PosDeviceResource;
use Filament\Actions;

class EditPosDevice extends EditRecord
{
    protected static string $resource = PosDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
