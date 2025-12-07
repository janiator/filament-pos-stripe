<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Filament\Resources\PosSessions\PosSessionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPosSession extends ViewRecord
{
    protected static string $resource = PosSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => $this->record->status !== 'closed'),
        ];
    }
}





