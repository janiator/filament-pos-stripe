<?php

namespace Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Positiv\FilamentWebflow\Actions\DiscoverCollections;
use Positiv\FilamentWebflow\Jobs\PullWebflowItems;
use Positiv\FilamentWebflow\Models\WebflowCollection;

class CollectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'collections';

    protected static ?string $title = 'CMS Collections';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('slug')->label('Slug'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('last_synced_at')
                    ->label('Last synced')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('discover')
                    ->label('Discover collections')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () {
                        $site = $this->getOwnerRecord();
                        if (empty($site->api_token)) {
                            Notification::make()
                                ->title('API token required')
                                ->body('Please add an API token to this site before discovering collections.')
                                ->danger()
                                ->send();

                            return;
                        }
                        $action = new DiscoverCollections;
                        $result = $action($site);
                        Notification::make()
                            ->title('Collections discovered')
                            ->body(($result['discovered'] ?? 0).' collection(s) found and saved.')
                            ->success()
                            ->send();
                        $recipient = auth()->user();
                        if ($recipient) {
                            Notification::make()
                                ->title('Collections discovered')
                                ->body(($result['discovered'] ?? 0).' collection(s) found and saved.')
                                ->success()
                                ->sendToDatabase($recipient);
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Fetch collections from Webflow?')
                    ->modalDescription('This will call the Webflow API and save all CMS collections for this site. Existing collections will be updated.'),
            ])
            ->actions([
                \Filament\Actions\Action::make('manageItems')
                    ->label('Manage items')
                    ->icon('heroicon-o-squares-2x2')
                    ->visible(fn (WebflowCollection $record): bool => $record->is_active)
                    ->url(fn (WebflowCollection $record): string => \Positiv\FilamentWebflow\Filament\Pages\WebflowCollectionItemsPage::getUrl(
                        ['collection' => $record->id],
                        true,
                        null,
                        $this->getOwnerRecord()->store
                    )),
                Action::make('activate')
                    ->label(fn (WebflowCollection $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (WebflowCollection $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->action(function (WebflowCollection $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                        if ($record->is_active) {
                            PullWebflowItems::dispatch($record);
                            $n = Notification::make()
                                ->title('Pull queued')
                                ->body("Items for collection \"{$record->name}\" will be synced from Webflow shortly.")
                                ->success();
                            $n->send();
                            $recipient = auth()->user();
                            if ($recipient) {
                                Notification::make()
                                    ->title('Pull queued')
                                    ->body("Items for collection \"{$record->name}\" will be synced from Webflow shortly.")
                                    ->success()
                                    ->sendToDatabase($recipient);
                            }
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
