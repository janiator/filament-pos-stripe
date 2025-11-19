<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ConnectedSubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('stripe_id'),
                TextEntry::make('stripe_status'),
                TextEntry::make('connected_price_id'),
                TextEntry::make('quantity')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('trial_ends_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('ends_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('stripe_customer_id'),
                TextEntry::make('stripe_account_id')
                    ->placeholder('-'),
            ]);
    }
}
