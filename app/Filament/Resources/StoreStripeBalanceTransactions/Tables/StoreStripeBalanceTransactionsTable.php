<?php

namespace App\Filament\Resources\StoreStripeBalanceTransactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StoreStripeBalanceTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store']))
            ->columns([
                TextColumn::make('type')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.type'))
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('formatted_fee')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.fee'))
                    ->badge()
                    ->color(fn ($record) => $record->fee > 0 ? 'warning' : 'gray')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('fee', $direction);
                    }),

                TextColumn::make('formatted_net')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.net'))
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('net', $direction);
                    }),

                TextColumn::make('stripe_charge_id')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.charge_id'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.status'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('store.name')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.store'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_balance_transaction_id')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.txn_id'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.synced'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('filament.resources.store_stripe_balance_transaction.columns.type'))
                    ->options([
                        'charge' => 'charge',
                        'stripe_fee' => 'stripe_fee',
                        'payout' => 'payout',
                        'refund' => 'refund',
                        'adjustment' => 'adjustment',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('stripe_created', 'desc')
            ->emptyStateHeading(__('filament.resources.store_stripe_balance_transaction.empty_heading'))
            ->emptyStateDescription(__('filament.resources.store_stripe_balance_transaction.empty_description'));
    }
}
