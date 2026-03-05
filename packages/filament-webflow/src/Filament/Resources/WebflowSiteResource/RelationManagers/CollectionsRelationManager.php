<?php

namespace Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Positiv\FilamentWebflow\Actions\DiscoverCollections;
use Positiv\FilamentWebflow\Jobs\PullWebflowItems;
use Positiv\FilamentWebflow\Models\WebflowCollection;
use Positiv\FilamentWebflow\Support\EventTicketFieldMapping;

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
                IconColumn::make('use_for_event_tickets')
                    ->label('Event tickets')
                    ->boolean()
                    ->trueIcon('heroicon-o-ticket')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success'),
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
                Action::make('useForEventTickets')
                    ->label(fn (WebflowCollection $record): string => $record->use_for_event_tickets ? 'Unset as event tickets' : 'Use for event tickets')
                    ->icon(fn (WebflowCollection $record): string => $record->use_for_event_tickets ? 'heroicon-o-minus-circle' : 'heroicon-o-ticket')
                    ->color(fn (WebflowCollection $record): string => $record->use_for_event_tickets ? 'gray' : 'success')
                    ->visible(fn (WebflowCollection $record): bool => ! $record->use_for_event_tickets)
                    ->form(fn (WebflowCollection $record): array => $this->fieldMappingFormSchema($record))
                    ->fillForm(fn (WebflowCollection $record): array => $this->fieldMappingFill($record))
                    ->modalHeading('Map CMS fields for event tickets')
                    ->modalDescription('Choose which CMS field (by slug) to use for each ticket data. Leave empty to use the default.')
                    ->action(function (array $data, WebflowCollection $record): void {
                        $storeId = $this->getOwnerRecord()->store_id;
                        WebflowCollection::query()
                            ->whereHas('site', fn ($q) => $q->where('store_id', $storeId))
                            ->where('id', '!=', $record->id)
                            ->update(['use_for_event_tickets' => false]);
                        $record->update([
                            'field_mapping' => array_filter($data, fn ($v) => $v !== null && $v !== ''),
                            'use_for_event_tickets' => true,
                        ]);
                        Notification::make()
                            ->title('Events collection set')
                            ->body("Items from \"{$record->name}\" will be used for Event Tickets. You can change the field mapping anytime via \"Configure field mapping\".")
                            ->success()
                            ->send();
                    }),
                Action::make('configureFieldMapping')
                    ->label('Configure field mapping')
                    ->icon('heroicon-o-map')
                    ->color('gray')
                    ->visible(fn (WebflowCollection $record): bool => (bool) $record->use_for_event_tickets)
                    ->form(fn (WebflowCollection $record): array => $this->fieldMappingFormSchema($record))
                    ->fillForm(fn (WebflowCollection $record): array => $this->fieldMappingFill($record))
                    ->modalHeading('Map CMS fields for event tickets')
                    ->modalDescription('Choose which CMS field (by slug) to use for each ticket data.')
                    ->action(function (array $data, WebflowCollection $record): void {
                        $record->update([
                            'field_mapping' => array_filter($data, fn ($v) => $v !== null && $v !== ''),
                        ]);
                        Notification::make()
                            ->title('Field mapping updated')
                            ->body('The CMS field mapping for event tickets has been saved.')
                            ->success()
                            ->send();
                    }),
                Action::make('unsetEventTickets')
                    ->label('Unset as event tickets')
                    ->icon('heroicon-o-minus-circle')
                    ->color('gray')
                    ->visible(fn (WebflowCollection $record): bool => (bool) $record->use_for_event_tickets)
                    ->requiresConfirmation()
                    ->modalHeading('Unset event tickets collection?')
                    ->modalDescription(fn (WebflowCollection $record): string => "Items from \"{$record->name}\" will no longer be used for Event Tickets. Field mapping is preserved.")
                    ->action(function (WebflowCollection $record): void {
                        $record->update(['use_for_event_tickets' => false]);
                        Notification::make()
                            ->title('Events collection unset')
                            ->body("\"{$record->name}\" is no longer the event tickets collection.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Build form schema for event ticket field mapping (Select per logical key).
     *
     * @return array<int, \Filament\Forms\Components\Select>
     */
    private function fieldMappingFormSchema(WebflowCollection $record): array
    {
        $schema = $record->schema ?? [];
        $options = ['' => '— None —'];
        foreach ($schema as $field) {
            $slug = $field['slug'] ?? null;
            if ($slug) {
                $label = $field['displayName'] ?? $slug;
                $options[$slug] = $label.' ('.$slug.')';
            }
        }

        $components = [];
        foreach (EventTicketFieldMapping::logicalKeys() as $logicalKey) {
            $label = str_replace('_', ' ', ucfirst($logicalKey));
            $components[] = Select::make($logicalKey)
                ->label($label)
                ->options($options)
                ->searchable()
                ->nullable();
        }

        return $components;
    }

    /**
     * Default fill for field mapping form (from record or defaults).
     *
     * @return array<string, string|null>
     */
    private function fieldMappingFill(WebflowCollection $record): array
    {
        $mapping = $record->field_mapping ?? [];
        $defaults = EventTicketFieldMapping::defaultMapping();
        $out = [];
        foreach (EventTicketFieldMapping::logicalKeys() as $key) {
            $out[$key] = $mapping[$key] ?? $defaults[$key] ?? null;
        }

        return $out;
    }
}
