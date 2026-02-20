<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use Database\Seeders\PaymentMethodSeeder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importDefaults')
                ->label(__('filament.actions.import_payment_method_defaults.label'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('filament.actions.import_payment_method_defaults.heading'))
                ->modalDescription(__('filament.actions.import_payment_method_defaults.description'))
                ->action(function (): void {
                    try {
                        $seeder = new PaymentMethodSeeder;
                        $seeder->run();

                        Notification::make()
                            ->success()
                            ->title(__('filament.actions.import_payment_method_defaults.title'))
                            ->body(__('filament.actions.import_payment_method_defaults.body'))
                            ->send();

                        $this->refresh();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title(__('filament.actions.import_payment_method_defaults.error_title'))
                            ->body(__('filament.actions.import_payment_method_defaults.error_body', ['message' => $e->getMessage()]))
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
