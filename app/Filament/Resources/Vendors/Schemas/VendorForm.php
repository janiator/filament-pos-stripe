<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Vendor Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('The name of the vendor'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Optional description for this vendor'),

                        TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Contact email address for this vendor'),

                        TextInput::make('contact_phone')
                            ->label('Contact Phone')
                            ->tel()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Contact phone number for this vendor'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active vendors are visible'),
                    ]),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Custom Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Additional metadata for this vendor')
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
