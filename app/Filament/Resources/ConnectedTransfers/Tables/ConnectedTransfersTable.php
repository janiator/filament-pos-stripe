<?php

namespace App\Filament\Resources\ConnectedTransfers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'charge']))
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('amount', $direction);
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'info' => 'in_transit',
                        'danger' => ['failed', 'canceled'],
                    ])
                    ->sortable(),

                TextColumn::make('arrival_date')
                    ->label('Arrival Date')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn ($record) => $record->arrival_date && $record->arrival_date->isPast() ? 'success' : null),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_transfer_id')
                    ->label('Transfer ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'in_transit' => 'In Transit',
                        'failed' => 'Failed',
                        'canceled' => 'Canceled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                // Transfers are immutable in Stripe
                // EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
