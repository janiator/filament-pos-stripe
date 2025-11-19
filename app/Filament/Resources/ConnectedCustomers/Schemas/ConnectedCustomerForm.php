<?php

namespace App\Filament\Resources\ConnectedCustomers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ConnectedCustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('model')
                    ->required(),
                TextInput::make('model_id')
                    ->numeric(),
                TextInput::make('model_uuid'),
                TextInput::make('stripe_customer_id')
                    ->required(),
                TextInput::make('stripe_account_id')
                    ->required(),
            ]);
    }
}
