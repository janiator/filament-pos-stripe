<?php

namespace App\Filament\Resources\TerminalReaders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TerminalReaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->maxLength(255),

                Select::make('terminal_location_id')
                    ->label('Location')
                    ->required()
                    ->relationship(
                        name: 'terminalLocation',
                        titleAttribute: 'display_name',
                        modifyQueryUsing: function ($query) {
                            try {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                if ($tenant) {
                                    $query->where('store_id', $tenant->id);
                                }
                            } catch (\Throwable $e) {
                                // Fallback if Filament facade not available
                            }
                            return $query;
                        }
                    ),

                Toggle::make('tap_to_pay')
                    ->label('Tap to Pay (no registration code)')
                    ->default(false),

                TextInput::make('registration_code')
                    ->label('Registration code')
                    ->helperText('Required for Bluetooth readers; not needed for Tap to Pay.')
                    ->required(fn (Get $get) => ! ($get('tap_to_pay') ?? false))
                    ->visible(fn (Get $get) => ! $get('tap_to_pay'))
                    ->visibleOn('create'),

                TextInput::make('stripe_reader_id')
                    ->label('Stripe reader ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Created on Stripe when this reader is registered.')
                    ->visibleOn(['view', 'edit']),

                TextInput::make('device_type')
                    ->label('Device Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['view', 'edit']),

                TextInput::make('status')
                    ->label('Status')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn(['view', 'edit']),
            ]);
    }
}
