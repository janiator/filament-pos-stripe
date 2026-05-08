<?php

namespace App\Filament\Resources\PowerOfficeAccountMappings\Pages;

use App\Filament\Resources\PowerOfficeAccountMappings\PowerOfficeAccountMappingResource;
use App\Models\PowerOfficeIntegration;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePowerOfficeAccountMapping extends CreateRecord
{
    protected static string $resource = PowerOfficeAccountMappingResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $store = Filament::getTenant();
        if ($store) {
            $data['store_id'] = $store->getKey();
            $integration = PowerOfficeIntegration::query()->where('store_id', $store->getKey())->first();
            if ($integration) {
                $data['power_office_integration_id'] = $integration->getKey();
                $data['basis_type'] = $integration->mapping_basis->value;
            }
        }

        return $data;
    }
}
