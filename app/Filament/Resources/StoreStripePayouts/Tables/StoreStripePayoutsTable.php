<?php

namespace App\Filament\Resources\StoreStripePayouts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StoreStripePayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store']))
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label(__('filament.resources.store_stripe_payout.columns.amount'))
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('amount', $direction);
                    }),

                TextColumn::make('status')
                    ->label(__('filament.resources.store_stripe_payout.columns.status'))
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'warning' => ['pending', 'in_transit'],
                        'danger' => ['failed', 'canceled'],
                    ])
                    ->sortable(),

                TextColumn::make('arrival_date')
                    ->label(__('filament.resources.store_stripe_payout.columns.arrival_date'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('method')
                    ->label(__('filament.resources.store_stripe_payout.columns.method'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('store.name')
                    ->label(__('filament.resources.store_stripe_payout.columns.store'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_payout_id')
                    ->label(__('filament.resources.store_stripe_payout.columns.payout_id'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament.resources.store_stripe_payout.columns.synced'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.resources.store_stripe_payout.columns.status'))
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'in_transit' => 'In transit',
                        'failed' => 'Failed',
                        'canceled' => 'Canceled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('arrival_date', 'desc')
            ->emptyStateHeading(__('filament.resources.store_stripe_payout.empty_heading'))
            ->emptyStateDescription(__('filament.resources.store_stripe_payout.empty_description'));
    }
}
