<?php

namespace App\Filament\Resources\PosDevices\RelationManagers;

use App\Models\TerminalLocation;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TerminalLocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'terminalLocations';

    protected static ?string $title = 'Terminal Location';

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
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('line2')
                    ->label('Address line 2')
                    ->maxLength(255),

                Forms\Components\TextInput::make('city')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('state')
                    ->label('State / County')
                    ->maxLength(255),

                Forms\Components\TextInput::make('postal_code')
                    ->label('Postal code')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('country')
                    ->label('Country (ISO 2-letter)')
                    ->required()
                    ->default('NO')
                    ->maxLength(2)
                    ->helperText('ISO 2-letter country code (e.g., NO, US)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stripe_location_id')
                    ->label('Stripe Location ID')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('terminalReaders_count')
                    ->label('Readers')
                    ->counts('terminalReaders')
                    ->badge()
                    ->color('info'),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('Assign terminal location')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('terminal_location_id')
                            ->label('Terminal Location')
                            ->helperText('Each POS device can have only one terminal location. Assigning a new one replaces the current assignment.')
                            ->options(function () {
                                /** @var \App\Models\PosDevice $posDevice */
                                $posDevice = $this->getOwnerRecord();

                                return TerminalLocation::where('store_id', $posDevice->store_id)
                                    ->where(function ($query) use ($posDevice) {
                                        $query->whereNull('pos_device_id')
                                            ->orWhere('pos_device_id', $posDevice->id);
                                    })
                                    ->pluck('display_name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->preload(),
                    ])
                    ->action(function (array $data): void {
                        /** @var \App\Models\PosDevice $posDevice */
                        $posDevice = $this->getOwnerRecord();
                        $locationId = (int) $data['terminal_location_id'];

                        TerminalLocation::where('pos_device_id', $posDevice->id)
                            ->where('id', '!=', $locationId)
                            ->update(['pos_device_id' => null]);

                        TerminalLocation::where('id', $locationId)
                            ->where('store_id', $posDevice->store_id)
                            ->update(['pos_device_id' => $posDevice->id]);
                    })
                    ->successNotificationTitle('Terminal location assigned'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                Action::make('detach')
                    ->label('Detach')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TerminalLocation $record): bool => $record->pos_device_id === $this->getOwnerRecord()->id)
                    ->action(function (TerminalLocation $record): void {
                        $record->update(['pos_device_id' => null]);
                    })
                    ->successNotificationTitle('Terminal location detached'),
            ]);
    }
}
