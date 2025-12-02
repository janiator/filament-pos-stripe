<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Filament\Resources\PosSessions\PosSessionResource;
use Filament\Resources\Pages\EditRecord;

class EditPosSession extends EditRecord
{
    protected static string $resource = PosSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // POS sessions should not be deletable for audit trail compliance
        ];
    }
}
