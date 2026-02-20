<?php

namespace App\Filament\Resources\Addons\Pages;

use App\Filament\Resources\Addons\AddonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAddon extends CreateRecord
{
    protected static string $resource = AddonResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['store_id'] = $data['store_id'] ?? \Filament\Facades\Filament::getTenant()?->id;

        return $data;
    }
}
