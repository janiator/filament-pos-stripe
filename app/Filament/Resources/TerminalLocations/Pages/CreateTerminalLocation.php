<?php

namespace App\Filament\Resources\TerminalLocations\Pages;

use App\Filament\Resources\TerminalLocations\TerminalLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTerminalLocation extends CreateRecord
{
    protected static string $resource = TerminalLocationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the current tenant (store)
        $tenant = \Filament\Facades\Filament::getTenant();
        
        if (!$tenant) {
            throw new \Exception('No tenant/store found. Terminal locations must be created within a store context.');
        }

        // Create the Terminal location on the CONNECTED ACCOUNT
        $location = $tenant->addTerminalLocation([
            'display_name' => $data['display_name'],
            'address' => [
                'line1'       => $data['line1'],
                'line2'       => $data['line2'] ?? null,
                'city'        => $data['city'],
                'state'       => $data['state'] ?? null,
                'country'     => $data['country'],
                'postal_code' => $data['postal_code'],
            ],
        ], true); // true = direct / connected account

        $data['stripe_location_id'] = $location->id;
        $data['store_id'] = $tenant->id;

        return $data;
    }
}
