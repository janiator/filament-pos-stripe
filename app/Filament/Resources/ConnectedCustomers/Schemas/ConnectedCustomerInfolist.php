<?php

namespace App\Filament\Resources\ConnectedCustomers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ConnectedCustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('model'),
                TextEntry::make('model_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('model_uuid')
                    ->placeholder('-'),
                TextEntry::make('stripe_customer_id'),
                TextEntry::make('stripe_account_id'),
            ]);
    }
}
