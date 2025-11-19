<?php

namespace App\Filament\Resources\ConnectedPrices\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedPriceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Prices are typically created through Stripe API or via products
                // This form is mainly for viewing/editing metadata
                
                Select::make('stripe_account_id')
                    ->label('Store')
                    ->options(function () {
                        return \App\Models\Store::whereNotNull('stripe_account_id')
                            ->pluck('name', 'stripe_account_id');
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Select::make('stripe_product_id')
                    ->label('Product')
                    ->options(function (Get $get) {
                        $accountId = $get('stripe_account_id');
                        if (! $accountId) {
                            return [];
                        }

                        return \App\Models\ConnectedProduct::where('stripe_account_id', $accountId)
                            ->get()
                            ->mapWithKeys(function ($product) {
                                return [$product->stripe_product_id => $product->name ?? $product->stripe_product_id];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                // Read-only display fields
                TextInput::make('formatted_amount')
                    ->label('Amount')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('type')
                    ->label('Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->visibleOn('edit'),

                TextInput::make('recurring_description')
                    ->label('Billing Interval')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->visible(fn ($record) => $record && $record->type === 'recurring'),

                TextInput::make('currency')
                    ->label('Currency')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->visibleOn('edit'),

                Toggle::make('active')
                    ->label('Active')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('nickname')
                    ->label('Nickname')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->visible(fn ($record) => $record && $record->nickname),

                TextInput::make('stripe_price_id')
                    ->label('Price ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }
}
