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
use Filament\Support\Icons\Heroicon;

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
            Action::make('generateReportsToken')
                ->label('Generate Reports Token')
                ->icon(Heroicon::OutlinedKey)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generate Reports API Token')
                ->modalDescription('This will replace the current reports token. Copy the new token and configure it in Merano under Settings → POS Rapporter.')
                ->visible(fn (): bool => StoreForm::supportsMeranoConfiguration()
                    && Addon::storeHasActiveAddon($this->record->id, AddonType::MeranoBooking))
                ->action(function (): void {
                    $token = bin2hex(random_bytes(32));
                    $this->record->update(['reports_api_token' => $token]);

                    Notification::make()
                        ->title('Reports token generated')
                        ->body($token)
                        ->success()
                        ->duration(30000)
                        ->send();
                }),
            Action::make('showReportsToken')
                ->label('Show Reports Token')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => StoreForm::supportsMeranoConfiguration()
                    && Addon::storeHasActiveAddon($this->record->id, AddonType::MeranoBooking)
                    && filled($this->record->reports_api_token))
                ->action(function (): void {
                    Notification::make()
                        ->title('Current Reports Token')
                        ->body($this->record->reports_api_token)
                        ->success()
                        ->duration(20000)
                        ->send();
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
