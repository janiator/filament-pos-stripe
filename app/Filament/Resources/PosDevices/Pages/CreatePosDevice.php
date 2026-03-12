<?php

namespace App\Filament\Resources\PosDevices\Pages;

use App\Filament\Resources\PosDevices\PosDeviceResource;
use App\Models\TerminalLocation;
use Filament\Resources\Pages\CreateRecord;

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

    protected function afterCreate(): void
    {
        $locationId = $this->form->getState()['terminal_location_id'] ?? null;
        if (! $locationId) {
            return;
        }
        $posDevice = $this->record;
        TerminalLocation::where('id', (int) $locationId)
            ->where('store_id', $posDevice->store_id)
            ->update(['pos_device_id' => $posDevice->id]);
    }
}
