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
        // Check if device already has an open session
        if (isset($data['pos_device_id']) && isset($data['status']) && $data['status'] === 'open') {
            $existingSession = PosSession::where('pos_device_id', $data['pos_device_id'])
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

        return $data;
    }
}
