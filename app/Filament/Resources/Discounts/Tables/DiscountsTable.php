<?php

namespace App\Filament\Resources\Discounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('formatted_value')
                    ->label('Discount')
                    ->badge()
                    ->color('success'),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Usage')
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => 
                        $record->usage_limit 
                            ? "{$state} / {$record->usage_limit}" 
                            : (string) $state
                    ),

                TextColumn::make('priority')
                    ->label('Priority')
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

                SelectFilter::make('discount_type')
                    ->label('Type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
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
            ->defaultSort('priority', 'desc');
    }
}
