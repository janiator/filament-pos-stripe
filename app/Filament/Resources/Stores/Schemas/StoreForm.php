<?php

namespace App\Filament\Resources\Stores\Schemas;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Radio::make('commission_type')
                    ->label('Commission type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed'      => 'Fixed (minor units)',
                    ])
                    ->default('percentage')
                    ->inline()
                    ->live(),

                TextInput::make('commission_rate')
                    ->label('Commission rate')
                    ->required()
                    ->numeric()
                    ->helperText(function (Get $get) {
                        return $get('commission_type') === 'percentage'
                            ? 'Enter whole percentage (e.g. 5 = 5%).'
                            : 'Enter fixed fee in minor units (e.g. 500 = 5.00).';
                    })
                    ->suffix(function (Get $get) {
                        return $get('commission_type') === 'percentage' ? '%' : 'units';
                    }),

                TextInput::make('stripe_account_id')
                    ->label('Stripe account ID')
                    ->helperText('Set automatically after connecting the store to Stripe (e.g. acct_xxx).')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
