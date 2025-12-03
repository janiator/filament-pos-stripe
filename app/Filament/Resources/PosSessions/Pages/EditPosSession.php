<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Filament\Resources\PosSessions\PosSessionResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPosSession extends EditRecord
{
    protected static string $resource = PosSessionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Prevent editing closed sessions
        if ($this->record->status === 'closed') {
            Notification::make()
                ->title('Cannot edit closed session')
                ->danger()
                ->body('Closed POS sessions cannot be edited for audit trail compliance.')
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Prevent saving changes to closed sessions
        if ($this->record->status === 'closed') {
            Notification::make()
                ->title('Cannot save changes')
                ->danger()
                ->body('Closed POS sessions cannot be modified for audit trail compliance.')
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // POS sessions should not be deletable for audit trail compliance
        ];
    }
}
