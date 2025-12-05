<?php

namespace App\Filament\Resources\ReceiptTemplates\Pages;

use App\Filament\Resources\ReceiptTemplates\ReceiptTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListReceiptTemplates extends ListRecords
{
    protected static string $resource = ReceiptTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seedFromFiles')
                ->label(__('filament.actions.seed_from_files.label'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('filament.actions.seed_from_files.heading'))
                ->modalDescription(__('filament.actions.seed_from_files.description'))
                ->action(function () {
                    Artisan::call('receipt-templates:seed');
                    
                    Notification::make()
                        ->success()
                        ->title(__('filament.actions.seed_from_files.title'))
                        ->body(__('filament.actions.seed_from_files.body'))
                        ->send();
                    
                    $this->refresh();
                }),
            CreateAction::make(),
        ];
    }
}
