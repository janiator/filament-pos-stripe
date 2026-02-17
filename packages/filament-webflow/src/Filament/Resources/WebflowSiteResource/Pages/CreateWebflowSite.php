<?php

namespace Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource;

class CreateWebflowSite extends CreateRecord
{
    protected static string $resource = WebflowSiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $data['store_id'] = $tenant->getKey();
        }

        return $data;
    }
}
