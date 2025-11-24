<?php

namespace App\Filament\Resources\PosDevices\Pages;

use App\Filament\Resources\PosDevices\PosDeviceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePosDevice extends CreateRecord
{
    protected static string $resource = PosDeviceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the store_id to the current tenant
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant) {
            $data['store_id'] = $tenant->id;
        }

        return $data;
    }
}

