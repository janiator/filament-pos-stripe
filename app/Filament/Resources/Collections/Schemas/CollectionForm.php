<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Collection Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                // Auto-generate handle from name when user finishes typing
                                if ($state) {
                                    $set('handle', Str::slug($state));
                                }
                            })
                            ->columnSpanFull()
                            ->helperText('The name of the collection'),

                        TextInput::make('handle')
                            ->label('Handle (Slug)')
                            ->maxLength(255)
                            ->helperText('URL-friendly identifier (e.g., "summer-collection"). Auto-updates as you type the name.')
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Optional description for this collection'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active collections are visible'),
                    ]),

                Section::make('Media')
                    ->schema([
                        TextInput::make('image_url')
                            ->label('Image URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Optional image URL for the collection')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(true),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Custom Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Additional metadata for this collection')
                            ->columnSpanFull()
                            ->addable(true)
                            ->deletable(true)
                            ->reorderable(false),
                    ])
                    ->collapsible()
                    ->collapsed(true),
            ]);
    }
}

