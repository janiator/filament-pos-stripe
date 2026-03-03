<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\TerminalLocation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TerminalLocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'terminalLocations';

    protected static ?string $title = 'Terminal Locations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('display_name')
                    ->label('Display name')
                    ->required()
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id
                        ? 'Location display name. This field will sync to Stripe when saved.'
                        : 'Location display name'),

                Forms\Components\TextInput::make('line1')
                    ->label('Address line 1')
                    ->required()
                    ->helperText(fn ($record) => $record && $record->stripe_location_id
                        ? 'Address line 1. This field will sync to Stripe when saved.'
                        : 'Address line 1'),

                Forms\Components\TextInput::make('line2')
                    ->label('Address line 2')
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id
                        ? 'Address line 2. This field will sync to Stripe when saved.'
                        : 'Address line 2'),

                Forms\Components\TextInput::make('city')
                    ->required()
                    ->helperText(fn ($record) => $record && $record->stripe_location_id
                        ? 'City. This field will sync to Stripe when saved.'
                        : 'City'),

                Forms\Components\TextInput::make('state')
                    ->label('State / County')
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id
                        ? 'State or county. This field will sync to Stripe when saved.'
                        : 'State or county'),

                Forms\Components\TextInput::make('postal_code')
                    ->label('Postal code')
                    ->required()
                    ->helperText(fn ($record) => $record && $record->stripe_location_id
                        ? 'Postal code. This field will sync to Stripe when saved.'
                        : 'Postal code'),

                Forms\Components\TextInput::make('country')
                    ->label('Country (ISO 2-letter)')
                    ->required()
                    ->default('US')
                    ->maxLength(2)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id
                        ? 'Country code (ISO 2-letter). This field will sync to Stripe when saved.'
                        : 'Country code (ISO 2-letter)'),

                Forms\Components\TextInput::make('stripe_location_id')
                    ->label('Stripe location ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Created on Stripe when this location is saved.'),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var \App\Models\Store $owner */
        $owner = $this->getOwnerRecord();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->sortable()
                    ->suffix(fn (TerminalLocation $record): string => $owner->default_terminal_location_id === $record->id ? ' (default for new POS devices)' : ''),

                Tables\Columns\TextColumn::make('city')
                    ->sortable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stripe_location_id')
                    ->label('Stripe location')
                    ->copyable()
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        /** @var \App\Models\Store $store */
                        $store = $this->getOwnerRecord();

                        // Create the Terminal location on the CONNECTED ACCOUNT
                        $location = $store->addTerminalLocation([
                            'display_name' => $data['display_name'],
                            'address' => [
                                'line1' => $data['line1'],
                                'line2' => $data['line2'] ?? null,
                                'city' => $data['city'],
                                'state' => $data['state'] ?? null,
                                'country' => $data['country'],
                                'postal_code' => $data['postal_code'],
                            ],
                        ], true); // true = direct / connected account

                        $data['stripe_location_id'] = $location->id;
                        $data['store_id'] = $store->id;

                        return $data;
                    }),
            ])
            ->actions([
                Action::make('setAsDefault')
                    ->label('Set as default')
                    ->icon('heroicon-o-star')
                    ->requiresConfirmation()
                    ->modalHeading('Set as default terminal location')
                    ->modalDescription('New POS devices registered for this store will be assigned to this terminal location. You can change this anytime.')
                    ->visible(fn (TerminalLocation $record): bool => $this->getOwnerRecord()->default_terminal_location_id !== $record->id)
                    ->action(function (TerminalLocation $record): void {
                        $store = $this->getOwnerRecord();
                        $store->update(['default_terminal_location_id' => $record->id]);
                        $store->refresh();
                    })
                    ->successNotificationTitle('Default terminal location updated'),

                EditAction::make()
                    ->mutateDataUsing(function (array $data, TerminalLocation $record): array {
                        /** @var \App\Models\Store $store */
                        $store = $this->getOwnerRecord();

                        // Optional: update location on Stripe if needed.
                        // if ($record->stripe_location_id) { ... }

                        return $data;
                    }),

                DeleteAction::make()
                    ->before(function (TerminalLocation $record): void {
                        /** @var \App\Models\Store $store */
                        $store = $this->getOwnerRecord();

                        // Optional: delete location on Stripe if desired.
                        // if ($record->stripe_location_id) { ... }
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
