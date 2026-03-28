<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class VerifoneTerminalsRelationManager extends RelationManager
{
    protected static string $relationship = 'verifoneTerminals';

    protected static ?string $title = 'Verifone Terminals';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('display_name')
                    ->label('Display name')
                    ->maxLength(255),

                TextInput::make('terminal_identifier')
                    ->label('Terminal identifier (POIID)')
                    ->required()
                    ->maxLength(255),

                Select::make('pos_device_id')
                    ->label('Linked POS device')
                    ->relationship(
                        name: 'posDevice',
                        titleAttribute: 'device_name',
                        modifyQueryUsing: function ($query) {
                            $store = $this->getOwnerRecord();
                            $query->where('store_id', $store->id);
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('sale_id')
                    ->label('Sale ID')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('operator_id')
                    ->label('Operator ID')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('site_entity_id')
                    ->label('Site entity ID')
                    ->maxLength(255)
                    ->nullable(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('terminal_identifier')
                    ->label('POIID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('posDevice.device_name')
                    ->label('POS device')
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sale_id')
                    ->label('Sale ID')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['store_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
