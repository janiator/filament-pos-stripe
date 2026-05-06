<?php

namespace App\Filament\Resources\ArticleGroupCodes\Schemas;

use Closure;
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
                            ->label(__('Code'))
                            ->required()
                            ->maxLength(10)
                            ->columnSpanFull()
                            ->helperText(__('PredefinedBasicID-04 code (e.g., "04001", "04003")'))
                            ->disabled(fn ($record) => $record && $record->is_standard)
                            ->rules([
                                'required',
                                'string',
                                'max:10',
                                fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                    if (! preg_match('/^04\d{3}$/', (string) $value)) {
                                        $fail('The code must be 5 digits starting with "04" (e.g., 04001, 04003).');
                                    }
                                },
                            ]),

                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText(__('Norwegian name for this article group code')),

                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText(__('Optional description for this article group code')),

                        TextInput::make('default_vat_percent')
                            ->label(__('Default VAT Percent (%)'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->columnSpan(1)
                            ->helperText(__('Default VAT percentage for products using this code'))
                            ->formatStateUsing(fn ($state) => $state !== null ? (float) $state * 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float) $state / 100 : null),

                        TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1)
                            ->helperText(__('Order for display in lists')),

                        Toggle::make('active')
                            ->label(__('Active'))
                            ->default(true)
                            ->helperText(__('Only active codes are available for selection'))
                            ->disabled(fn ($record) => $record && $record->is_standard),

                        Toggle::make('show_in_pos')
                            ->label(__('Visible in POS'))
                            ->default(true)
                            ->helperText(__('When enabled, this code appears in the POS app (e.g. in product edit). When disabled, it is hidden from POS.')),

                        Toggle::make('is_standard')
                            ->label(__('Standard Code'))
                            ->default(false)
                            ->helperText(__('Standard SAF-T codes are pre-seeded and cannot be deleted'))
                            ->disabled(fn ($record) => $record && $record->is_standard),
                    ]),
            ]);
    }
}
