<?php

namespace App\Filament\Resources\EventTickets\Pages;

use App\Actions\EventTickets\ImportEventTicketsFromWebflowCollection;
use App\Filament\Resources\EventTickets\EventTicketResource;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEventTickets extends ListRecords
{
    protected static string $resource = EventTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromWebflow')
                ->label('Sync from Webflow')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->form([
                    Checkbox::make('pull_first')
                        ->label('Pull from Webflow first (sync latest CMS items before importing)')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $store = Filament::getTenant();
                    if (! $store instanceof Store) {
                        Notification::make()
                            ->title('No store selected')
                            ->body('Please select a store (tenant) first.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $import = app(ImportEventTicketsFromWebflowCollection::class);
                    $result = $import($store, null, (bool) ($data['pull_first'] ?? false));

                    Notification::make()
                        ->title('Sync complete')
                        ->body("Created: {$result['created']}, Updated: {$result['updated']}.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
