<?php

namespace App\Filament\Resources\PosEvents\Pages;

use App\Filament\Resources\PosEvents\PosEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPosEvent extends EditRecord
{
    protected static string $resource = PosEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
