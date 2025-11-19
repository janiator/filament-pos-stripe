<?php

namespace App\Filament\Resources\ConnectedCustomers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedCustomerForm
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
                    ->helperText('The store/connected account this customer belongs to')
                    ->live()
                    ->visibleOn('create'),

                TextInput::make('stripe_customer_id')
                    ->label('Stripe Customer ID')
                    ->required()
                    ->helperText('The Stripe customer ID from the connected account')
                    ->visibleOn('create'),

                TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255)
                    ->helperText('Customer name')
                    ->visibleOn(['create', 'edit']),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->helperText('Customer email address')
                    ->visibleOn(['create', 'edit']),

                // Model mapping fields (optional)
                TextInput::make('model')
                    ->label('Model Class')
                    ->helperText('Optional: The model class this customer is mapped to')
                    ->visibleOn(['create', 'edit']),

                TextInput::make('model_id')
                    ->label('Model ID')
                    ->numeric()
                    ->helperText('Optional: The model ID this customer is mapped to')
                    ->visibleOn(['create', 'edit'])
                    ->visible(fn (Get $get) => $get('model')),

                TextInput::make('model_uuid')
                    ->label('Model UUID')
                    ->helperText('Optional: The model UUID this customer is mapped to')
                    ->visibleOn(['create', 'edit'])
                    ->visible(fn (Get $get) => $get('model')),

                // Read-only fields on edit
                TextInput::make('stripe_customer_id')
                    ->label('Customer ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('stripe_account_id')
                    ->label('Account ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }
}
