<?php

namespace App\Filament\Resources\ConnectedCustomers\Pages;

use App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource;
use App\Filament\Resources\Pages\EditRecord;
use App\Models\ConnectedCustomer;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;

class EditConnectedCustomer extends EditRecord
{
    protected static string $resource = ConnectedCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('archive')
                ->label(__('Archive'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Archive customer?'))
                ->modalDescription(__('The customer will be hidden from the POS customer list. Purchase history is kept.'))
                ->modalSubmitActionLabel(__('Archive'))
                ->hidden(fn (ConnectedCustomer $record): bool => $record->isArchived())
                ->action(function (ConnectedCustomer $record): void {
                    $record->archive();
                    $this->redirect(ConnectedCustomerResource::getUrl('index'));
                }),
        ];
    }
}
