<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ConnectedPaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Note: Payment methods are typically created via Stripe.js or Payment Intents
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

                Select::make('stripe_customer_id')
                    ->label('Customer')
                    ->options(function (Get $get) {
                        $accountId = $get('stripe_account_id');
                        if (! $accountId) {
                            return [];
                        }

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
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                // Read-only display fields
                TextInput::make('card_display')
                    ->label('Payment Method')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('type')
                    ->label('Type')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->visibleOn('edit'),

                Toggle::make('is_default')
                    ->label('Default Payment Method')
                    ->helperText('Mark this as the default payment method for the customer. Setting this to true will unset other default payment methods for this customer.')
                    ->visibleOn('edit'),

                TextInput::make('billing_details_name')
                    ->label('Billing Name')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('billing_details_email')
                    ->label('Billing Email')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

                TextInput::make('stripe_payment_method_id')
                    ->label('Payment Method ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }
}
