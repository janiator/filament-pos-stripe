<?php

namespace App\Filament\Resources\TerminalReaders\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TerminalReaderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('store_id')
                    ->numeric(),
                TextEntry::make('terminal_location_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('stripe_reader_id')
                    ->placeholder('-'),
                TextEntry::make('serial_number')
                    ->label('Serial number')
                    ->placeholder('-'),
                TextEntry::make('label'),
                IconEntry::make('tap_to_pay')
                    ->boolean(),
                TextEntry::make('device_type')
                    ->placeholder('-'),
                TextEntry::make('status')
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
