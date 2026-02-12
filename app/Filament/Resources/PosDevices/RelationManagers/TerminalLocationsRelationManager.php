<?php

namespace App\Filament\Resources\PosDevices\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use App\Models\TerminalLocation;
use Filament\Actions\Action;

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
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
                Action::make('attach')
                    ->label('Attach Existing Location')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('terminal_location_id')
                            ->label('Terminal Location')
                            ->options(function () {
                                /** @var \App\Models\PosDevice $posDevice */
                                $posDevice = $this->getOwnerRecord();
                                
                                return TerminalLocation::where('store_id', $posDevice->store_id)
                                    ->where(function ($query) use ($posDevice) {
                                        $query->whereNull('pos_device_id')
                                              ->orWhere('pos_device_id', '!=', $posDevice->id);
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
                        
                        TerminalLocation::where('id', $data['terminal_location_id'])
                            ->where('store_id', $posDevice->store_id)
                            ->update(['pos_device_id' => $posDevice->id]);
                    })
                    ->successNotificationTitle('Terminal location attached'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                Action::make('detach')
                    ->label('Detach')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TerminalLocation $record): bool => $record->pos_device_id === $this->getOwnerRecord()->id)
                    ->action(function (TerminalLocation $record): void {
                        // Detach by setting pos_device_id to null
                        $record->update(['pos_device_id' => null]);
                    })
                    ->successNotificationTitle('Terminal location detached'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('detach')
                        ->label('Detach Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            /** @var \App\Models\PosDevice $posDevice */
                            $posDevice = $this->getOwnerRecord();
                            $ids = $records->filter(fn (TerminalLocation $r) => $r->pos_device_id === $posDevice->id)->pluck('id');
                            if ($ids->isNotEmpty()) {
                                TerminalLocation::whereIn('id', $ids)->update(['pos_device_id' => null]);
                            }
                        })
                        ->successNotificationTitle('Terminal locations detached'),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                /** @var \App\Models\PosDevice $owner */
                $owner = $this->getOwnerRecord();
                // If no locations are attached to this device, show the store's terminal locations instead
                if (! $owner->terminalLocations()->exists()) {
                    return TerminalLocation::query()
                        ->where('store_id', $owner->store_id)
                        ->withCount('terminalReaders');
                }
                return $query->withCount('terminalReaders');
            });
    }
}

