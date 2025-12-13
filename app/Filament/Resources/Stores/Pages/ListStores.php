<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Actions\Stores\SyncStoresFromStripe;
use App\Filament\Resources\Stores\StoreResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('onboard')
                ->label('Onboard Store')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->color('success')
                ->url(StoreResource::getUrl('onboard'))
                ->visible(fn () => $this->canOnboardStore()),

            CreateAction::make(),

            Action::make('syncStripeAccounts')
                ->label('Sync Stripe accounts')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () {
                    app(SyncStoresFromStripe::class)(notify: true);
                }),
        ];
    }

    protected function canOnboardStore(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant) {
                return $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists();
            }
            return $user->hasRole('super_admin');
        } catch (\Throwable $e) {
            return $user->hasRole('super_admin');
        }
    }
}
