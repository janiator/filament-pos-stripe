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
                    ->default(fn () => \Filament\Facades\Filament::getTenant()?->stripe_account_id)
                    ->helperText('The store/connected account this charge belongs to')
                    ->live()
                    ->hiddenOn(['create', 'edit']),

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
                            ->whereNotNull('stripe_customer_id')
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
                    ->helperText('Select a customer from this store. If the customer has no payment methods, you must provide a payment method below.')
                    ->live()
                    ->visibleOn('create'),

                Select::make('stripe_payment_method_id')
                    ->label('Payment Method')
                    ->key('stripe_payment_method_id')
                    ->options(function (Get $get) {
                        $accountId = $get('stripe_account_id');
                        $customerId = $get('stripe_customer_id');
                        
                        if (! $accountId || ! $customerId) {
                            return [];
                        }

                        // Check if ConnectedPaymentMethod model exists
                        if (!class_exists(\App\Models\ConnectedPaymentMethod::class)) {
                            return [];
                        }

                        $paymentMethods = \App\Models\ConnectedPaymentMethod::where('stripe_account_id', $accountId)
                            ->where('stripe_customer_id', $customerId)
                            ->get();

                        if ($paymentMethods->isEmpty()) {
                            return [];
                        }

                        return $paymentMethods->mapWithKeys(function ($paymentMethod) {
                            $label = $paymentMethod->card_display;
                            if ($paymentMethod->is_default) {
                                $label .= ' (Default)';
                            }
                            return [$paymentMethod->stripe_payment_method_id => $label];
                        });
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText(function (Get $get) {
                        $accountId = $get('stripe_account_id');
                        $customerId = $get('stripe_customer_id');
                        
                        if (! $customerId) {
                            return 'Select a customer first to see their payment methods.';
                        }
                        
                        if (! $accountId) {
                            return 'Select a store first.';
                        }
                        
                        // Check if customer has payment methods
                        if (class_exists(\App\Models\ConnectedPaymentMethod::class)) {
                            $hasPaymentMethods = \App\Models\ConnectedPaymentMethod::where('stripe_account_id', $accountId)
                                ->where('stripe_customer_id', $customerId)
                                ->exists();
                            
                            if (! $hasPaymentMethods) {
                                return '⚠️ This customer has no payment methods saved. You must add a payment method to Stripe first, or the charge will fail.';
                            }
                        }
                        
                        return 'Optional: Select a specific payment method. If not provided, Stripe will attempt to use the customer\'s default payment method.';
                    })
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
