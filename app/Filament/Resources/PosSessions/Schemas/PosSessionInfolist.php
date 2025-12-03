<?php

namespace App\Filament\Resources\PosSessions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PosSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Session Information')
                    ->schema([
                        TextEntry::make('session_number')
                            ->label('Session Number')
                            ->weight('bold')
                            ->size('lg'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'success',
                                'closed' => 'gray',
                                default => 'gray',
                            }),

                        TextEntry::make('store.name')
                            ->label('Store')
                            ->visible(function () {
                                try {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    return $tenant && $tenant->slug === 'visivo-admin';
                                } catch (\Throwable $e) {
                                    return false;
                                }
                            }),

                        TextEntry::make('posDevice.device_name')
                            ->label('POS Device'),

                        TextEntry::make('user.name')
                            ->label('Cashier'),
                    ])
                    ->columns(2),

                Section::make('Timing')
                    ->schema([
                        TextEntry::make('opened_at')
                            ->label('Opened At')
                            ->dateTime(),

                        TextEntry::make('closed_at')
                            ->label('Closed At')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Cash Management')
                    ->schema([
                        TextEntry::make('opening_balance')
                            ->label('Opening Balance')
                            ->money('nok', divideBy: 100)
                            ->suffix('kr')
                            ->placeholder('-'),

                        TextEntry::make('expected_cash')
                            ->label('Expected Cash')
                            ->money('nok', divideBy: 100)
                            ->suffix('kr')
                            ->placeholder('-'),

                        TextEntry::make('actual_cash')
                            ->label('Actual Cash')
                            ->money('nok', divideBy: 100)
                            ->suffix('kr')
                            ->placeholder('-'),

                        TextEntry::make('cash_difference')
                            ->label('Cash Difference')
                            ->money('nok', divideBy: 100)
                            ->suffix('kr')
                            ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('opening_notes')
                            ->label('Opening Notes')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('closing_notes')
                            ->label('Closing Notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Session Statistics')
                    ->schema([
                        TextEntry::make('transaction_count')
                            ->label('Transactions')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->money('nok', divideBy: 100)
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}

