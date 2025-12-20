<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Filament\Resources\PosSessions\PosSessionResource;
use Filament\Resources\Pages\ListRecords;

class EmbedPosSessions extends ListRecords
{
    protected static string $resource = PosSessionResource::class;

    protected static bool $shouldRegisterNavigation = false;

    protected function getHeaderActions(): array
    {
        return []; // No header actions for embed view
    }
}



