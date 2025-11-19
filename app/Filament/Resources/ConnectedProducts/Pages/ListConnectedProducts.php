<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnectedProducts extends ListRecords
{
    protected static string $resource = ConnectedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
