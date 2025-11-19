<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConnectedSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('stripe_id')
                    ->required(),
                TextInput::make('stripe_status')
                    ->required(),
                TextInput::make('connected_price_id')
                    ->required(),
                TextInput::make('quantity')
                    ->numeric(),
                DateTimePicker::make('trial_ends_at'),
                DateTimePicker::make('ends_at'),
                TextInput::make('stripe_customer_id')
                    ->required(),
                TextInput::make('stripe_account_id'),
            ]);
    }
}
