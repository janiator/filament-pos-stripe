<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedSubscriptionForm
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
                    ->helperText('The store/connected account this subscription belongs to')
                    ->live()
                    ->visibleOn('create'),

                Select::make('stripe_customer_id')
                    ->label('Customer')
                    ->options(function (Get $get) {
                        $accountId = $get('stripe_account_id');
                        if (! $accountId) {
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
                    ->required()
                    ->helperText('Select a customer from this store')
                    ->visibleOn('create'),

                Select::make('connected_price_id')
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
                    ->required()
                    ->helperText('Subscription name')
                    ->visibleOn('create'),

                TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->helperText('Number of units')
                    ->visibleOn('create'),

                DateTimePicker::make('trial_ends_at')
                    ->label('Trial Ends At')
                    ->helperText('Optional: When the trial period ends')
                    ->visibleOn('create'),

                // Read-only fields on edit
                TextInput::make('name')
                    ->label('Name')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('stripe_status')
                    ->label('Status')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('stripe_id')
                    ->label('Subscription ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('connected_price_id')
                    ->label('Price ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('quantity')
                    ->label('Quantity')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                DateTimePicker::make('trial_ends_at')
                    ->label('Trial Ends At')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                DateTimePicker::make('ends_at')
                    ->label('Ends At')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                Toggle::make('cancel_at_period_end')
                    ->label('Cancel at Period End')
                    ->helperText('Whether to cancel the subscription at the end of the current period. This will sync to Stripe when saved.')
                    ->visibleOn('edit')
                    ->visible(fn ($record) => isset($record->cancel_at_period_end)),

                KeyValue::make('metadata')
                    ->label('Metadata')
                    ->helperText('Custom key-value pairs. This field will sync to Stripe when saved.')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->visibleOn('edit'),

                // Note: Most subscription fields cannot be synced to Stripe
                // Only cancel_at_period_end and metadata can be updated via API
                // Other fields (status, price, quantity, etc.) are managed by Stripe
            ]);
    }
}
