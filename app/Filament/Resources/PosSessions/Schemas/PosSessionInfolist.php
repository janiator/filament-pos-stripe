<?php

namespace App\Filament\Resources\PosSessions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                            ->placeholder('-'),

                        TextEntry::make('expected_cash')
                            ->label('Expected Cash')
                            ->money('nok', divideBy: 100)
                            ->placeholder('-'),

                        TextEntry::make('actual_cash')
                            ->label('Actual Cash')
                            ->money('nok', divideBy: 100)
                            ->placeholder('-'),

                        TextEntry::make('cash_difference')
                            ->label('Cash Difference')
                            ->money('nok', divideBy: 100)
                            ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                            ->placeholder('-'),
                    ])
                    ->columns(2)
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
                    ->columns(2),

                Section::make('Payment Method Breakdown')
                    ->schema([
                        TextEntry::make('payment_breakdown')
                            ->label('')
                            ->state(function ($record) {
                                $charges = $record->charges()->where('status', 'succeeded')->get();
                                
                                if ($charges->isEmpty()) {
                                    return 'No transactions';
                                }
                                
                                $breakdown = [
                                    'cash' => ['count' => 0, 'amount' => 0],
                                    'card' => ['count' => 0, 'amount' => 0],
                                    'mobile' => ['count' => 0, 'amount' => 0],
                                    'other' => ['count' => 0, 'amount' => 0],
                                ];
                                
                                foreach ($charges as $charge) {
                                    $method = $charge->payment_method;
                                    $amount = $charge->amount ?? 0;
                                    
                                    if ($method === 'cash') {
                                        $breakdown['cash']['count']++;
                                        $breakdown['cash']['amount'] += $amount;
                                    } elseif (in_array($method, ['card_present', 'card'])) {
                                        $breakdown['card']['count']++;
                                        $breakdown['card']['amount'] += $amount;
                                    } elseif (in_array($method, ['vipps', 'mobile'])) {
                                        $breakdown['mobile']['count']++;
                                        $breakdown['mobile']['amount'] += $amount;
                                    } else {
                                        $breakdown['other']['count']++;
                                        $breakdown['other']['amount'] += $amount;
                                    }
                                }
                                
                                // Format the breakdown directly as a string
                                $lines = [];
                                if ($breakdown['cash']['count'] > 0) {
                                    $lines[] = "Cash: {$breakdown['cash']['count']} transactions, " . number_format($breakdown['cash']['amount'] / 100, 2) . ' NOK';
                                }
                                if ($breakdown['card']['count'] > 0) {
                                    $lines[] = "Card: {$breakdown['card']['count']} transactions, " . number_format($breakdown['card']['amount'] / 100, 2) . ' NOK';
                                }
                                if ($breakdown['mobile']['count'] > 0) {
                                    $lines[] = "Mobile: {$breakdown['mobile']['count']} transactions, " . number_format($breakdown['mobile']['amount'] / 100, 2) . ' NOK';
                                }
                                if ($breakdown['other']['count'] > 0) {
                                    $lines[] = "Other: {$breakdown['other']['count']} transactions, " . number_format($breakdown['other']['amount'] / 100, 2) . ' NOK';
                                }
                                
                                return empty($lines) ? 'No transactions' : implode("\n", $lines);
                            })
                            ->columnSpanFull()
                            ->placeholder('No transactions'),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record->charges()->where('status', 'succeeded')->count() > 0),

                Section::make('Transactions')
                    ->schema([
                        RepeatableEntry::make('charges')
                            ->label('')
                            ->state(function ($record) {
                                return $record->charges()
                                    ->where('status', 'succeeded')
                                    ->orderBy('paid_at', 'desc')
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->map(function ($charge) {
                                        return [
                                            'id' => $charge->id,
                                            'stripe_charge_id' => $charge->stripe_charge_id,
                                            'amount' => $charge->amount,
                                            'payment_method' => $charge->payment_method,
                                            'payment_code' => $charge->payment_code,
                                            'transaction_code' => $charge->transaction_code,
                                            'description' => $charge->description,
                                            'paid_at' => $charge->paid_at,
                                            'created_at' => $charge->created_at,
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->schema([
                                TextEntry::make('stripe_charge_id')
                                    ->label('Charge ID')
                                    ->copyable()
                                    ->placeholder('-'),
                                
                                TextEntry::make('amount')
                                    ->label('Amount')
                                    ->money('nok', divideBy: 100)
                                    ->badge()
                                    ->color('success'),
                                
                                TextEntry::make('payment_method')
                                    ->label('Payment Method')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state ?? 'Unknown'))),
                                
                                TextEntry::make('payment_code')
                                    ->label('Payment Code')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('-'),
                                
                                TextEntry::make('transaction_code')
                                    ->label('Transaction Code')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('-'),
                                
                                TextEntry::make('description')
                                    ->label('Description')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                
                                TextEntry::make('paid_at')
                                    ->label('Paid At')
                                    ->dateTime()
                                    ->placeholder('-'),
                                
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime()
                                    ->placeholder('-'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->collapsible()
                    ->visible(fn ($record) => $record->charges()->where('status', 'succeeded')->count() > 0),

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
            ]);
    }
}

