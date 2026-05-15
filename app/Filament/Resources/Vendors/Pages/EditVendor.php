<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class EditVendor extends EditRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('archive')
                ->label(__('Archive'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Archive vendor?'))
                ->modalDescription(__('The vendor will be hidden from the POS catalog and product pickers. Historical sales and product links are kept.'))
                ->modalSubmitActionLabel(__('Archive'))
                ->hidden(fn (Vendor $record): bool => $record->isArchived())
                ->action(function (Vendor $record): void {
                    $record->archive();
                    $this->redirect(VendorResource::getUrl('index'));
                }),
        ];
    }
}
