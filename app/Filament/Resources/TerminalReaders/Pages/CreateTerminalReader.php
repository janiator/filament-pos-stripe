<?php

namespace App\Filament\Resources\TerminalReaders\Pages;

use App\Filament\Resources\TerminalReaders\TerminalReaderResource;
use App\Models\TerminalLocation;
use Filament\Resources\Pages\CreateRecord;

class CreateTerminalReader extends CreateRecord
{
    protected static string $resource = TerminalReaderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the current tenant (store)
        $tenant = \Filament\Facades\Filament::getTenant();
        
        if (!$tenant) {
            throw new \Exception('No tenant/store found. Terminal readers must be created within a store context.');
        }

        /** @var TerminalLocation $location */
        $location = TerminalLocation::where('id', $data['terminal_location_id'])
            ->where('store_id', $tenant->id)
            ->firstOrFail();

        $params = [
            'label'    => $data['label'],
            'location' => $location->stripe_location_id,
        ];

        $tapToPay = $data['tap_to_pay'] ?? false;
        
        if (! $tapToPay) {
            // Registration code is required for non-Tap-to-Pay readers
            if (empty($data['registration_code'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'registration_code' => 'Registration code is required for Bluetooth readers.',
                ]);
            }
            $params['registration_code'] = $data['registration_code'];
        }

        // Register reader on the CONNECTED ACCOUNT
        $reader = $tenant->registerTerminalReader($params, true);

        $data['stripe_reader_id'] = $reader->id;
        $data['store_id'] = $tenant->id;
        $data['device_type'] = $reader->device_type ?? null;
        $data['status'] = $reader->status ?? null;

        return $data;
    }
}
