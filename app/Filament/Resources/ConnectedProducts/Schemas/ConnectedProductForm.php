<?php

namespace App\Filament\Resources\ConnectedProducts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedProductForm
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
                    ->helperText('The store/connected account this product belongs to')
                    ->live()
                    ->visibleOn('create'),

                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Product name')
                    ->visibleOn('create'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->helperText('Product description')
                    ->visibleOn('create'),

                Select::make('type')
                    ->label('Type')
                    ->options([
                        'service' => 'Service',
                        'good' => 'Good',
                    ])
                    ->default('service')
                    ->helperText('Product type')
                    ->visibleOn('create'),

                Toggle::make('active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Whether the product is active')
                    ->visibleOn('create'),

                // Read-only fields on edit
                TextInput::make('name')
                    ->label('Name')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Textarea::make('description')
                    ->label('Description')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Toggle::make('active')
                    ->label('Active')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('type')
                    ->label('Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Service')
                    ->visibleOn('edit'),

                TextInput::make('stripe_product_id')
                    ->label('Product ID')
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
