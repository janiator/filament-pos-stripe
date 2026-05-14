<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\PosDevice;
use App\Models\PosSession;
use Filament\Notifications\Notification;

class CreatePosSession extends CreateRecord
{
    protected static string $resource = PosSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        $storeId = null;
        if ($tenant && $tenant->slug !== 'visivo-admin') {
            $storeId = (int) $tenant->id;
        } elseif (! empty($data['pos_device_id'])) {
            $device = PosDevice::query()->find((int) $data['pos_device_id']);
            $storeId = $device?->store_id;
        }

        if (! $storeId) {
            $storeId = auth()->user()?->currentStore()?->id;
        }

        if (! $storeId) {
            Notification::make()
                ->title('Cannot create session')
                ->danger()
                ->body('No store could be resolved (select a POS device).')
                ->send();

            $this->halt();
        }

        unset($data['store_id']);

        if (isset($data['pos_device_id']) && isset($data['status']) && $data['status'] === 'open') {
            $existingSession = PosSession::query()
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

        if (empty($data['session_number'])) {
            $lastSession = PosSession::forStore((int) $storeId)
                ->orderBy('session_number', 'desc')
                ->first();

            $sessionNumber = $lastSession
                ? (int) $lastSession->session_number + 1
                : 1;

            $data['session_number'] = str_pad((string) $sessionNumber, 6, '0', STR_PAD_LEFT);
        }

        if (empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        if (empty($data['opened_at'])) {
            $data['opened_at'] = now();
        }

        return $data;
    }
}
