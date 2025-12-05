<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\TerminalLocation;
use App\Models\TerminalReader;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TerminalReadersRelationManager extends RelationManager
{
    protected static string $relationship = 'terminalReaders';

    protected static ?string $title = 'Terminal Readers';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('terminal_location_id')
                    ->label('Location')
                    ->required()
                    ->relationship(
                        name: 'terminalLocation',
                        titleAttribute: 'display_name',
                        modifyQueryUsing: function ($query) {
                            $store = $this->getOwnerRecord();
                            $query->where('store_id', $store->id);
                        }
                    ),

                Forms\Components\Toggle::make('tap_to_pay')
                    ->label('Tap to Pay (no registration code)')
                    ->default(false),

                Forms\Components\TextInput::make('registration_code')
                    ->label('Registration code')
                    ->helperText('Required for Bluetooth readers; not needed for Tap to Pay.')
                    ->required(fn (Get $get) => ! ($get('tap_to_pay') ?? false))
                    ->visible(fn (Get $get) => ! $get('tap_to_pay')),

                Forms\Components\TextInput::make('stripe_reader_id')
                    ->label('Stripe reader ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Created on Stripe when this reader is registered.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('terminalLocation.display_name')
                    ->label('Location')
                    ->sortable(),

                Tables\Columns\IconColumn::make('tap_to_pay')
                    ->label('Tap to Pay')
                    ->boolean(),

                Tables\Columns\TextColumn::make('stripe_reader_id')
                    ->label('Stripe reader')
                    ->copyable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        /** @var \App\Models\Store $store */
                        $store = $this->getOwnerRecord();

                        /** @var TerminalLocation $location */
                        $location = TerminalLocation::where('id', $data['terminal_location_id'])
                            ->where('store_id', $store->id)
                            ->firstOrFail();

                        $params = [
                            'label'    => $data['label'],
                            'location' => $location->stripe_location_id,
                        ];

                        $tapToPay = $data['tap_to_pay'] ?? false;
                        
                        if (! $tapToPay) {
                            // Registration code is required for non-Tap-to-Pay readers
                            if (empty($data['registration_code'] ?? null)) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'registration_code' => 'Registration code is required for Bluetooth readers.',
                                ]);
                            }
                            $params['registration_code'] = $data['registration_code'];
                        }

                        // Register reader on the CONNECTED ACCOUNT
                        $reader = $store->registerTerminalReader($params, true);

                        $data['stripe_reader_id'] = $reader->id;
                        $data['store_id'] = $store->id;
                        $data['device_type'] = $reader->device_type ?? null;
                        $data['status'] = $reader->status ?? null;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data, TerminalReader $record): array {
                        /** @var \App\Models\Store $store */
                        $store = $this->getOwnerRecord();

                        // Optional: update reader in Stripe here.

                        return $data;
                    }),

                DeleteAction::make()
                    ->before(function (TerminalReader $record): void {
                        /** @var \App\Models\Store $store */
                        $store = $this->getOwnerRecord();

                        // Optional: deactivate/delete reader on Stripe here.
                    }),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
