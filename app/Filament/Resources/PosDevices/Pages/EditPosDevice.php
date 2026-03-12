<?php

namespace App\Filament\Resources\PosDevices\Pages;

use App\Filament\Resources\PosDevices\PosDeviceResource;
use App\Models\TerminalLocation;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosDevice extends EditRecord
{
    protected static string $resource = PosDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['terminal_location_id'] = $this->record->terminalLocations->first()?->id;

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncTerminalLocationFromForm();
    }

    private function syncTerminalLocationFromForm(): void
    {
        $locationId = $this->form->getState()['terminal_location_id'] ?? null;
        $posDevice = $this->record;

        TerminalLocation::where('pos_device_id', $posDevice->id)
            ->where('id', '!=', (int) $locationId)
            ->update(['pos_device_id' => null]);

        if ($locationId) {
            TerminalLocation::where('id', (int) $locationId)
                ->where('store_id', $posDevice->store_id)
                ->update(['pos_device_id' => $posDevice->id]);
        }
    }
}
