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
                ->label('Seed from Files')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Seed Templates from Files')
                ->modalDescription('This will create or update global templates from the template files. Custom templates will be skipped unless you use --force.')
                ->action(function () {
                    Artisan::call('receipt-templates:seed');
                    
                    Notification::make()
                        ->success()
                        ->title('Templates seeded successfully')
                        ->body('Global templates have been seeded from files.')
                        ->send();
                    
                    $this->refresh();
                }),
            CreateAction::make(),
        ];
    }
}
