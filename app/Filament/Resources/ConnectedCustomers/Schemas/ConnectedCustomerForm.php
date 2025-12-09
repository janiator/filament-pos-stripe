<?php

namespace App\Filament\Resources\ConnectedCustomers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
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
                    ->helperText(fn ($record) => $record && $record->stripe_customer_id 
                        ? 'Customer name. This field will sync to Stripe when saved.'
                        : 'Customer name')
                    ->visibleOn(['create', 'edit']),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_customer_id 
                        ? 'Customer email address. This field will sync to Stripe when saved.'
                        : 'Customer email address')
                    ->visibleOn(['create', 'edit']),

                TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(255)
                    ->helperText(fn ($record) => $record && $record->stripe_customer_id 
                        ? 'Customer phone number. This field will sync to Stripe when saved.'
                        : 'Customer phone number')
                    ->visibleOn(['create', 'edit']),

                TextInput::make('profile_image_url')
                    ->label('Profile Image URL')
                    ->url()
                    ->maxLength(500)
                    ->helperText('URL to the customer profile image')
                    ->visibleOn(['create', 'edit']),

                Section::make('Address')
                    ->schema([
                        TextInput::make('address.line1')
                            ->label('Address Line 1')
                            ->maxLength(255)
                            ->helperText('Street address, PO Box, or company name')
                            ->visibleOn(['create', 'edit']),

                        TextInput::make('address.line2')
                            ->label('Address Line 2')
                            ->maxLength(255)
                            ->helperText('Apartment, suite, unit, or building')
                            ->visibleOn(['create', 'edit']),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('address.city')
                                    ->label('City')
                                    ->maxLength(255)
                                    ->visibleOn(['create', 'edit']),

                                TextInput::make('address.state')
                                    ->label('State / County')
                                    ->maxLength(255)
                                    ->visibleOn(['create', 'edit']),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('address.postal_code')
                                    ->label('Postal Code')
                                    ->maxLength(255)
                                    ->visibleOn(['create', 'edit']),

                                TextInput::make('address.country')
                                    ->label('Country (ISO 2-letter)')
                                    ->maxLength(2)
                                    ->helperText('Two-letter country code (e.g., NO, US)')
                                    ->visibleOn(['create', 'edit']),
                            ]),
                    ])
                    ->collapsible()
                    ->visibleOn(['create', 'edit']),

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
