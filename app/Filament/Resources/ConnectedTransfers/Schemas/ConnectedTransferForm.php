<?php

namespace App\Filament\Resources\ConnectedTransfers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedTransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stripe_account_id')
                    ->label('Store')
                    ->options(function () {
                        return \App\Models\Store::whereNotNull('stripe_account_id')
                            ->pluck('name', 'stripe_account_id');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn () => \Filament\Facades\Filament::getTenant()?->stripe_account_id)
                    ->helperText('The store/connected account this transfer belongs to')
                    ->live()
                    ->hiddenOn(['create', 'edit']),

                TextInput::make('amount')
                    ->label('Amount (in cents)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('Enter amount in cents (e.g., 1000 = $10.00)')
                    ->visibleOn('create'),

                Select::make('currency')
                    ->label('Currency')
                    ->options([
                        'nok' => 'NOK',
                        'usd' => 'USD',
                        'eur' => 'EUR',
                        'gbp' => 'GBP',
                        'cad' => 'CAD',
                        'aud' => 'AUD',
                    ])
                    ->default('nok')
                    ->required()
                    ->visibleOn('create'),

                TextInput::make('destination')
                    ->label('Destination Account ID')
                    ->helperText('Optional: Destination Stripe account ID. Defaults to the store account.')
                    ->visibleOn('create'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->helperText('Optional: A description for this transfer')
                    ->visibleOn('create'),

                // Read-only fields on edit
                TextInput::make('stripe_transfer_id')
                    ->label('Transfer ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('formatted_amount')
                    ->label('Amount')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('status')
                    ->label('Status')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('arrival_date')
                    ->label('Arrival Date')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i:s') : '-')
                    ->visibleOn('edit'),

                TextInput::make('description')
                    ->label('Description')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }
}
