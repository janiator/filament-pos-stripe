<?php

namespace App\Filament\Resources\ConnectedCharges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedChargeForm
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
                    ->helperText('The store/connected account this charge belongs to')
                    ->live()
                    ->visibleOn('create'),

                Select::make('stripe_customer_id')
                    ->label('Customer')
                    ->options(function (Get $get) {
                        $accountId = $get('stripe_account_id');
                        if (! $accountId) {
                            return [];
                        }

                        // Check if ConnectedCustomer model exists
                        if (!class_exists(\App\Models\ConnectedCustomer::class)) {
                            return [];
                        }

                        return \App\Models\ConnectedCustomer::where('stripe_account_id', $accountId)
                            ->get()
                            ->mapWithKeys(function ($customer) {
                                $label = $customer->name ?? $customer->email ?? $customer->stripe_customer_id;
                                if ($customer->email && $customer->name) {
                                    $label = "{$customer->name} ({$customer->email})";
                                } elseif ($customer->email) {
                                    $label = $customer->email;
                                }
                                
                                return [$customer->stripe_customer_id => $label];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->helperText('Select a customer from this store')
                    ->visibleOn('create'),

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
                        'usd' => 'USD',
                        'eur' => 'EUR',
                        'gbp' => 'GBP',
                        'cad' => 'CAD',
                        'aud' => 'AUD',
                    ])
                    ->default('usd')
                    ->required()
                    ->visibleOn('create'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->helperText('Optional: A description for this charge')
                    ->visibleOn('create'),

                // Read-only fields on edit
                TextInput::make('stripe_charge_id')
                    ->label('Charge ID')
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

                TextInput::make('charge_type')
                    ->label('Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->visibleOn('edit'),

                TextInput::make('payment_method')
                    ->label('Payment Method')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                    ->visibleOn('edit'),

                TextInput::make('paid_at')
                    ->label('Paid At')
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
