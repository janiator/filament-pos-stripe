<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Filament\Resources\PosSessions\PosSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosSession extends EditRecord
{
    protected static string $resource = PosSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
