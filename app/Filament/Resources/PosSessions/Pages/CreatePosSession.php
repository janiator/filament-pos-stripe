<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\PosSession;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreatePosSession extends CreateRecord
{
    protected static string $resource = PosSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get current tenant/store
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant && $tenant->slug !== 'visivo-admin') {
            $data['store_id'] = $tenant->id;
        }

        // Ensure store_id is set
        if (!isset($data['store_id'])) {
            $data['store_id'] = $tenant?->id ?? auth()->user()?->currentStore()?->id;
        }

        if (!$data['store_id']) {
            Notification::make()
                ->title('Cannot create session')
                ->danger()
                ->body('No store selected')
                ->send();

            $this->halt();
        }

        // Check if device already has an open session
        if (isset($data['pos_device_id']) && isset($data['status']) && $data['status'] === 'open') {
            $existingSession = PosSession::where('store_id', $data['store_id'])
                ->where('pos_device_id', $data['pos_device_id'])
                ->where('status', 'open')
                ->first();

            if ($existingSession) {
                Notification::make()
                    ->title('Cannot open session')
                    ->danger()
                    ->body("Device already has an open session: {$existingSession->session_number}")
                    ->send();

                $this->halt();
            }
        }

        // Generate session number if not provided
        if (empty($data['session_number'])) {
            $lastSession = PosSession::where('store_id', $data['store_id'])
                ->orderBy('session_number', 'desc')
                ->first();

            $sessionNumber = $lastSession 
                ? (int) $lastSession->session_number + 1 
                : 1;

            $data['session_number'] = str_pad($sessionNumber, 6, '0', STR_PAD_LEFT);
        }

        // Set default user if not provided
        if (empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        // Set opened_at if not provided
        if (empty($data['opened_at'])) {
            $data['opened_at'] = now();
        }

        return $data;
    }
}
