<?php

namespace App\Filament\Resources\PosSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PosSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_number')
                    ->label('Session #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        'abandoned' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),
                TextColumn::make('posDevice.device_name')
                    ->label('Device')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable(),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Closed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('transaction_count')
                    ->label('Transactions')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('nok', divideBy: 100)
                    ->sortable(),
                TextColumn::make('cash_difference')
                    ->label('Cash Diff')
                    ->money('nok', divideBy: 100)
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                        'abandoned' => 'Abandoned',
                    ]),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // No bulk actions for sessions
                ]),
            ]);
    }
}
