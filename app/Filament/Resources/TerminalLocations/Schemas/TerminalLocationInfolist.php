<?php

namespace App\Filament\Resources\TerminalLocations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TerminalLocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('store_id')
                    ->numeric(),
                TextEntry::make('stripe_location_id')
                    ->placeholder(__('-')),
                TextEntry::make('display_name'),
                TextEntry::make('line1'),
                TextEntry::make('line2')
                    ->placeholder(__('-')),
                TextEntry::make('city'),
                TextEntry::make('state')
                    ->placeholder(__('-')),
                TextEntry::make('postal_code'),
                TextEntry::make('country'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder(__('-')),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder(__('-')),
            ]);
    }
}
