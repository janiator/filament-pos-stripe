<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedPaymentLinkForm
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
                    ->helperText('The store/connected account this payment link belongs to')
                    ->live()
                    ->visibleOn('create'),

                Select::make('stripe_price_id')
                    ->label('Price')
                    ->options(function (Get $get) {
                        $accountId = $get('stripe_account_id');
                        if (! $accountId) {
                            return [];
                        }

                        return \App\Models\ConnectedPrice::where('stripe_account_id', $accountId)
                            ->where('active', true)
                            ->with('product')
                            ->get()
                            ->mapWithKeys(function ($price) {
                                $productName = $price->product?->name ?? 'Unknown Product';
                                $amount = $price->formatted_amount;
                                $recurring = $price->recurring_description;
                                $label = "{$productName} - {$amount}";
                                if ($recurring) {
                                    $label .= " ({$recurring})";
                                }
                                return [$price->stripe_price_id => $label];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Select a price from this store')
                    ->visibleOn('create'),

                TextInput::make('name')
                    ->label('Name')
                    ->maxLength(255)
                    ->helperText('Optional: A name for this payment link')
                    ->visibleOn('create'),

                Select::make('link_type')
                    ->label('Link Type')
                    ->options([
                        'direct' => 'Direct',
                        'destination' => 'Destination',
                    ])
                    ->default('direct')
                    ->required()
                    ->live()
                    ->helperText('Direct: Charge goes directly to connected account. Destination: Charge goes to platform with transfer.')
                    ->visibleOn('create'),

                TextInput::make('application_fee_percent')
                    ->label('Application Fee (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->helperText('For destination links: Percentage fee (e.g., 5 = 5%)')
                    ->visible(fn (Get $get) => $get('link_type') === 'destination')
                    ->visibleOn('create'),

                TextInput::make('application_fee_amount')
                    ->label('Application Fee (cents)')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('For destination links: Fixed fee in cents (e.g., 500 = $5.00)')
                    ->visible(fn (Get $get) => $get('link_type') === 'destination')
                    ->visibleOn('create'),

                TextInput::make('after_completion_redirect_url')
                    ->label('Redirect URL')
                    ->url()
                    ->helperText('Optional: URL to redirect to after payment completion')
                    ->visibleOn('create'),

                // Read-only fields on edit
                TextInput::make('stripe_payment_link_id')
                    ->label('Payment Link ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('name')
                    ->label('Name')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('url')
                    ->label('URL')
                    ->disabled()
                    ->dehydrated(false)
                    ->url()
                    ->visibleOn('edit'),

                Toggle::make('active')
                    ->label('Active')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('link_type')
                    ->label('Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->visibleOn('edit'),
            ]);
    }
}
