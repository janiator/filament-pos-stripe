<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Pages;

use App\Actions\ConnectedPaymentLinks\UpdateConnectedPaymentLinkInStripe;
use App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConnectedPaymentLink extends EditRecord
{
    protected static string $resource = ConnectedPaymentLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->label('Deactivate')
                ->requiresConfirmation()
                ->modalHeading('Deactivate Payment Link')
                ->modalDescription('Are you sure you want to deactivate this payment link? It will be deactivated in Stripe but not deleted. You can reactivate it later.')
                ->action(function () {
                    $this->record->active = false;
                    $this->record->save();

                    $action = new UpdateConnectedPaymentLinkInStripe();
                    $action($this->record, true);

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        // Sync active status changes to Stripe
        $action = new UpdateConnectedPaymentLinkInStripe();
        $action($this->record, true);
    }
}
