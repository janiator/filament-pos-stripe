<?php

namespace App\Filament\Resources\ArticleGroupCodes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleGroupCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(10)
                            ->columnSpanFull()
                            ->helperText('PredefinedBasicID-04 code (e.g., "04001", "04003")')
                            ->disabled(fn ($record) => $record && $record->is_standard)
                            ->rules([
                                'required',
                                'string',
                                'max:10',
                                function ($attribute, $value, $fail) {
                                    // Validate code format (should be 5 digits starting with 04)
                                    if (!preg_match('/^04\d{3}$/', $value)) {
                                        $fail('The code must be 5 digits starting with "04" (e.g., 04001, 04003).');
                                    }
                                },
                            ]),

                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Norwegian name for this article group code'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Optional description for this article group code'),

                        TextInput::make('default_vat_percent')
                            ->label('Default VAT Percent (%)')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->columnSpan(1)
                            ->helperText('Default VAT percentage for products using this code')
                            ->formatStateUsing(fn ($state) => $state !== null ? (float) $state * 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state / 100 : null),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1)
                            ->helperText('Order for display in lists'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active codes are available for selection')
                            ->disabled(fn ($record) => $record && $record->is_standard),

                        Toggle::make('is_standard')
                            ->label('Standard Code')
                            ->default(false)
                            ->helperText('Standard SAF-T codes are pre-seeded and cannot be deleted')
                            ->disabled(fn ($record) => $record && $record->is_standard),
                    ]),
            ]);
    }
}
