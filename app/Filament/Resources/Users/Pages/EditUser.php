<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function clearAllTokens(): void
    {
        $tokenCount = $this->record->tokens()->count();
        
        if ($tokenCount === 0) {
            Notification::make()
                ->title('No tokens to clear')
                ->warning()
                ->send();
            return;
        }

        $this->record->tokens()->delete();

        // Refresh the record to update relationships
        $this->record->refresh();

        Notification::make()
            ->title('All API tokens cleared')
            ->body("Successfully revoked {$tokenCount} token(s).")
            ->success()
            ->send();
    }
}
