<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()
                ->record($this->getRecord())
                ->redirectTo(fn (): string => $this->getRecord()->impersonationRedirectUrl()),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function clearAllTokens(): void
    {
        $tokenCount = $this->record->tokens()->count();

        if ($tokenCount === 0) {
            Notification::make()
                ->title(__('No tokens to clear'))
                ->warning()
                ->send();

            return;
        }

        $this->record->tokens()->delete();

        // Refresh the record to update relationships
        $this->record->refresh();

        Notification::make()
            ->title(__('All API tokens cleared'))
            ->body("Successfully revoked {$tokenCount} token(s).")
            ->success()
            ->send();
    }
}
