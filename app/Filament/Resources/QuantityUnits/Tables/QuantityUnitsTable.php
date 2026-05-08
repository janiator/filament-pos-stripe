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
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('symbol')
                    ->label(__('Symbol'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('description')
                    ->label(__('Description'))
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('products_count')
                    ->label(__('Products'))
                    ->counts('products')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                IconColumn::make('is_standard')
                    ->label(__('Standard'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),

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

                TernaryFilter::make('is_standard')
                    ->label(__('Standard Units'))
                    ->placeholder(__('All'))
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
