<?php

namespace App\Filament\Resources\Coupons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('formatted_value')
                    ->label('Discount')
                    ->badge()
                    ->color('success'),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->badge()
                    ->formatStateUsing(fn ($state, $record) => 
                        $state === 'repeating' && $record->duration_in_months
                            ? "{$state} ({$record->duration_in_months} months)"
                            : $state
                    ),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('times_redeemed')
                    ->label('Redemptions')
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->max_redemptions 
                            ? "{$state} / {$record->max_redemptions}" 
                            : (string) $state
                    ),

                TextColumn::make('redeem_by')
                    ->label('Redeem By')
                    ->dateTime()
                    ->sortable(),

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

                SelectFilter::make('duration')
                    ->label('Duration')
                    ->options([
                        'once' => 'Once',
                        'repeating' => 'Repeating',
                        'forever' => 'Forever',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
