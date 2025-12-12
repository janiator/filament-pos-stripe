<?php

namespace App\Filament\Resources\ProductDeclarations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductDeclarationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Generell informasjon')
                    ->schema([
                        Select::make('store_id')
                            ->relationship('store', 'name', modifyQueryUsing: function ($query) {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant && $tenant->slug !== 'visivo-admin') {
                                        $query->where('stores.id', $tenant->id);
                                    }
                                } catch (\Throwable $e) {
                                    // Fallback if Filament facade not available
                                }
                            })
                            ->required()
                            ->default(fn () => \Filament\Facades\Filament::getTenant()?->id)
                            ->searchable()
                            ->preload()
                            ->visible(function () {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    return $tenant && $tenant->slug === 'visivo-admin';
                                } catch (\Throwable $e) {
                                    return false;
                                }
                            })
                            ->disabled(fn ($record) => $record !== null)
                            ->helperText('Butikk som denne produktfråsegna gjelder for'),

                        TextInput::make('product_name')
                            ->label('Produktnavn')
                            ->required()
                            ->default('POS Stripe Backend - Kassasystem')
                            ->maxLength(255),

                        TextInput::make('vendor_name')
                            ->label('Leverandør')
                            ->maxLength(255)
                            ->helperText('Navn på leverandør av kassasystemet'),

                        TextInput::make('version')
                            ->label('Versjon')
                            ->required()
                            ->default('1.0.0')
                            ->maxLength(50),

                        TextInput::make('version_identification')
                            ->label('Versjonsidentifikasjon')
                            ->required()
                            ->default('POS-STRIPE-BACKEND-1.0.0')
                            ->maxLength(255)
                            ->helperText('Unik identifikasjon for denne versjonen'),

                        DatePicker::make('declaration_date')
                            ->label('Dato for produktfråsegn')
                            ->default(now())
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Aktiv')
                            ->default(true)
                            ->helperText('Kun én aktiv produktfråsegn per butikk'),
                    ])
                    ->columns(2),

                Section::make('Innhold')
                    ->schema([
                        MarkdownEditor::make('content')
                            ->label('Produktfråsegn innhold')
                            ->required()
                            ->helperText('Full produktfråsegn i Markdown-format. Dette vil vises i POS-systemet.')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'link',
                                'heading',
                                'bulletList',
                                'orderedList',
                                'codeBlock',
                                'blockquote',
                            ])
                            ->fileAttachmentsDirectory('product-declarations'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
