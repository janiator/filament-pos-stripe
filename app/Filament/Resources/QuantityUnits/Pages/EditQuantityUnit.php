<?php

namespace App\Filament\Resources\QuantityUnits\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\QuantityUnits\QuantityUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditQuantityUnit extends EditRecord
{
    protected static string $resource = QuantityUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn (): bool => $this->record->is_standard),
        ];
    }
}
