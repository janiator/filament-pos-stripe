<?php

namespace App\Filament\Resources\QuantityUnits\Pages;

use App\Filament\Resources\QuantityUnits\QuantityUnitResource;
use Database\Seeders\QuantityUnitSeeder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListQuantityUnits extends ListRecords
{
    protected static string $resource = QuantityUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importDefaults')
                ->label(__('filament.actions.import_quantity_unit_defaults.label'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('filament.actions.import_quantity_unit_defaults.heading'))
                ->modalDescription(__('filament.actions.import_quantity_unit_defaults.description'))
                ->action(function (): void {
                    try {
                        $seeder = new QuantityUnitSeeder;
                        $seeder->run();

                        Notification::make()
                            ->success()
                            ->title(__('filament.actions.import_quantity_unit_defaults.title'))
                            ->body(__('filament.actions.import_quantity_unit_defaults.body'))
                            ->send();

                        $this->refresh();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('filament.actions.import_quantity_unit_defaults.error_title'))
                            ->body(__('filament.actions.import_quantity_unit_defaults.error_body', ['message' => $e->getMessage()]))
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
