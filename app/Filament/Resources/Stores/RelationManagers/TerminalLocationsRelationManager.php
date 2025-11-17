<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\TerminalLocation;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;

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
                    ->maxLength(255),

                Forms\Components\TextInput::make('line1')
                    ->label('Address line 1')
                    ->required(),

                Forms\Components\TextInput::make('line2')
                    ->label('Address line 2')
                    ->maxLength(255),

                Forms\Components\TextInput::make('city')
                    ->required(),

                Forms\Components\TextInput::make('state')
                    ->label('State / County')
                    ->maxLength(255),

                Forms\Components\TextInput::make('postal_code')
                    ->label('Postal code')
                    ->required(),

                Forms\Components\TextInput::make('country')
                    ->label('Country (ISO 2-letter)')
                    ->required()
                    ->default('US')
                    ->maxLength(2),

                Forms\Components\TextInput::make('stripe_location_id')
                    ->label('Stripe location ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Created on Stripe when this location is saved.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->sortable(),

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
                                'line1'       => $data['line1'],
                                'line2'       => $data['line2'] ?? null,
                                'city'        => $data['city'],
                                'state'       => $data['state'] ?? null,
                                'country'     => $data['country'],
                                'postal_code' => $data['postal_code'],
                            ],
                        ], true); // true = direct / connected account

                        $data['stripe_location_id'] = $location->id;
                        $data['store_id'] = $store->id;

                        return $data;
                    }),
            ])
            ->actions([
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
