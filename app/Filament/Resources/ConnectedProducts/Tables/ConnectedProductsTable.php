<?php

namespace App\Filament\Resources\ConnectedProducts\Tables;

use App\Models\ConnectedProduct;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ConnectedProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'prices', 'variants']))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('-'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(function ($state, ConnectedProduct $record) {
                        if (!$state) {
                            // Try to get from default_price
                            if ($record->default_price && $record->stripe_account_id) {
                                $defaultPrice = \App\Models\ConnectedPrice::where('stripe_price_id', $record->default_price)
                                    ->where('stripe_account_id', $record->stripe_account_id)
                                    ->first();
                                
                                if ($defaultPrice && $defaultPrice->unit_amount) {
                                    $currency = strtoupper($defaultPrice->currency ?? 'NOK');
                                    return number_format($defaultPrice->unit_amount / 100, 2, '.', '') . ' ' . $currency;
                                }
                            }
                            return '-';
                        }
                        
                        $currency = strtoupper($record->currency ?? 'NOK');
                        // Price is already in decimal format from the accessor
                        return number_format((float) $state, 2, '.', '') . ' ' . $currency;
                    }),

                TextColumn::make('compare_at_price_amount')
                    ->label('Compare at Price')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(function ($state, ConnectedProduct $record) {
                        if (!$state) {
                            return '-';
                        }
                        $currency = strtoupper($record->currency ?? 'NOK');
                        return number_format($state / 100, 2, '.', '') . ' ' . $currency;
                    })
                    ->toggleable(),

                TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(function ($state) {
                        return $state ? $state . '%' : '-';
                    })
                    ->toggleable(),

                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?: '0'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Service')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->url(fn (ConnectedProduct $record) => $record->store
                        ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_product_id')
                    ->label('Product ID')
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
                TernaryFilter::make('active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                SelectFilter::make('stripe_account_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
