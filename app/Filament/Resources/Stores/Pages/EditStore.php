<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Enums\AddonType;
use App\Filament\Resources\Stores\Schemas\StoreForm;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Addon;
use App\Services\MeranoConnectionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('testMeranoConnection')
                ->label('Test Merano Connection')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn (): bool => StoreForm::supportsMeranoConfiguration()
                    && Addon::storeHasActiveAddon($this->record->id, AddonType::MeranoBooking))
                ->action(function (): void {
                    $result = app(MeranoConnectionService::class)->testConnection($this->record);

                    $notification = Notification::make()
                        ->title($result['ok'] ? 'Merano connection successful' : 'Merano connection failed')
                        ->body($result['message'])
                        ->duration(15000);

                    if ($result['ok']) {
                        $notification->success();
                    } else {
                        $notification->danger();
                    }

                    $notification->send();
                }),
            DeleteAction::make(),
        ];
    }

    // Sync is handled by model event listener in Store::booted()
    // protected function afterSave(): void
    // {
    //     app(SyncStoreToStripe::class)($this->record);
    // }
}
