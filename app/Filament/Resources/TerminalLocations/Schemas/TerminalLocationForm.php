<?php

namespace App\Filament\Resources\TerminalLocations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TerminalLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('display_name')
                    ->label('Display name')
                    ->required()
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id 
                        ? 'Location display name. This field will sync to Stripe when saved.'
                        : 'Location display name'),

                TextInput::make('line1')
                    ->label('Address line 1')
                    ->required()
                    ->helperText(fn ($record) => $record && $record->stripe_location_id 
                        ? 'Address line 1. This field will sync to Stripe when saved.'
                        : 'Address line 1'),

                TextInput::make('line2')
                    ->label('Address line 2')
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id 
                        ? 'Address line 2. This field will sync to Stripe when saved.'
                        : 'Address line 2'),

                TextInput::make('city')
                    ->required()
                    ->helperText(fn ($record) => $record && $record->stripe_location_id 
                        ? 'City. This field will sync to Stripe when saved.'
                        : 'City'),

                TextInput::make('state')
                    ->label('State / County')
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id 
                        ? 'State or county. This field will sync to Stripe when saved.'
                        : 'State or county'),

                TextInput::make('postal_code')
                    ->label('Postal code')
                    ->required()
                    ->helperText(fn ($record) => $record && $record->stripe_location_id 
                        ? 'Postal code. This field will sync to Stripe when saved.'
                        : 'Postal code'),

                TextInput::make('country')
                    ->label('Country (ISO 2-letter)')
                    ->required()
                    ->default('US')
                    ->maxLength(2)
                    ->helperText(fn ($record) => $record && $record->stripe_location_id 
                        ? 'Country code (ISO 2-letter). This field will sync to Stripe when saved.'
                        : 'Country code (ISO 2-letter)'),

                TextInput::make('stripe_location_id')
                    ->label('Stripe location ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Created on Stripe when this location is saved.')
                    ->visibleOn(['view', 'edit']),
            ]);
    }
}
