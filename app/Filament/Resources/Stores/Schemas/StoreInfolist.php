<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\Store;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StoreInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),

                TextEntry::make('email')
                    ->label('Email'),

                TextEntry::make('commission_type')
                    ->label('Commission type'),

                TextEntry::make('commission_rate')
                    ->label('Commission')
                    ->formatStateUsing(function (Store $record): string {
                        if ($record->commission_type === 'percentage') {
                            return "{$record->commission_rate}%";
                        }

                        return number_format($record->commission_rate / 100, 2);
                    }),

                TextEntry::make('stripe_account_id')
                    ->label('Stripe account ID')
                    ->placeholder('-'),

                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),

                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
