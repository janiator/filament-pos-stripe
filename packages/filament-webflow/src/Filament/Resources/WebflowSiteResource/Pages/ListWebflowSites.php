<?php

namespace Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource;

class ListWebflowSites extends ListRecords
{
    protected static string $resource = WebflowSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
