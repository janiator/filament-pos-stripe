<?php

namespace App\Filament\Resources\ArticleGroupCodes\Pages;

use App\Filament\Resources\ArticleGroupCodes\ArticleGroupCodeResource;
use Database\Seeders\ArticleGroupCodeSeeder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListArticleGroupCodes extends ListRecords
{
    protected static string $resource = ArticleGroupCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seedSafTCodes')
                ->label(__('filament.actions.seed_saf_t_codes.label'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('filament.actions.seed_saf_t_codes.heading'))
                ->modalDescription(__('filament.actions.seed_saf_t_codes.description'))
                ->action(function () {
                    try {
                        $seeder = new ArticleGroupCodeSeeder();
                        $seeder->run();
                        
                        Notification::make()
                            ->success()
                            ->title(__('filament.actions.seed_saf_t_codes.title'))
                            ->body(__('filament.actions.seed_saf_t_codes.body'))
                            ->send();
                        
                        $this->refresh();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Feil ved import')
                            ->body('Kunne ikke importere SAF-T koder: ' . $e->getMessage())
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
