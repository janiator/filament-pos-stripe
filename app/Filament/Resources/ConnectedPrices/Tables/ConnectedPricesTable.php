<?php

namespace App\Filament\Resources\ConnectedPrices\Tables;

use App\Models\ConnectedPrice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ConnectedPricesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'product']))
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label(__('Amount'))
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('unit_amount', $direction);
                    }),

                TextColumn::make('product.name')
                    ->label(__('Product'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder(__('-'))
                    ->url(fn (ConnectedPrice $record) => $record->product && class_exists(\App\Filament\Resources\ConnectedProducts\ConnectedProductResource::class)
                        ? \App\Filament\Resources\ConnectedProducts\ConnectedProductResource::getUrl('view', ['record' => $record->product])
                        : null),

                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->colors([
                        'success' => 'recurring',
                        'info' => 'one_time',
                    ])
                    ->sortable(),

                TextColumn::make('recurring_description')
                    ->label(__('Billing Interval'))
                    ->badge()
                    ->color('info')
                    ->placeholder(__('-'))
                    ->visible(fn (ConnectedPrice $record) => $record->type === 'recurring'),

                TextColumn::make('currency')
                    ->label(__('Currency'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('nickname')
                    ->label(__('Nickname'))
                    ->searchable()
                    ->placeholder(__('-'))
                    ->toggleable(),

                TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_price_id')
                    ->label(__('Price ID'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('Active'))
                    ->placeholder(__('All'))
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options([
                        'one_time' => 'One Time',
                        'recurring' => 'Recurring',
                    ]),

                SelectFilter::make('stripe_account_id')
                    ->label(__('Store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                // Prices are immutable in Stripe
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
