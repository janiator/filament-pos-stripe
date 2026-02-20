<?php

namespace App\Filament\Resources\QuantityUnits\Tables;

use App\Models\QuantityUnit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class QuantityUnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('symbol')
                    ->label('Symbol')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                IconColumn::make('is_standard')
                    ->label('Standard')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
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

                TernaryFilter::make('is_standard')
                    ->label('Standard Units')
                    ->placeholder('All')
                    ->trueLabel('Standard only')
                    ->falseLabel('Custom only'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (QuantityUnit $record): bool => $record->is_standard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $custom = $records->filter(fn (QuantityUnit $r): bool => ! $r->is_standard);
                            $custom->each->delete();
                            if ($custom->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title(__('filament.resources.quantity_unit.bulk_delete_standard_only'))
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
