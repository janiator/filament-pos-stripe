<?php

namespace App\Filament\Resources\QuantityUnits\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuantityUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('The name of the quantity unit (e.g., "Piece", "Kilogram", "Meter")'),

                        TextInput::make('symbol')
                            ->label('Symbol')
                            ->maxLength(20)
                            ->columnSpanFull()
                            ->helperText('The symbol or abbreviation (e.g., "stk", "kg", "m")'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Optional description for this quantity unit'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active quantity units are available for selection'),

                        Toggle::make('is_standard')
                            ->label('Standard Unit')
                            ->default(false)
                            ->helperText('Standard units are pre-seeded and cannot be deleted')
                            ->disabled(fn ($record) => $record && $record->is_standard),
                    ]),
            ]);
    }
}
