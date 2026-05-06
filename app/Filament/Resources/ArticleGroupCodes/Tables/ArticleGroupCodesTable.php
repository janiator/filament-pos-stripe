<?php

namespace App\Filament\Resources\ArticleGroupCodes\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ArticleGroupCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('Code'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label(__('Description'))
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('default_vat_percent')
                    ->label(__('Default VAT %'))
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state * 100, 2) : '—')
                    ->suffix(__('%'))
                    ->sortable()
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

                IconColumn::make('show_in_pos')
                    ->label(__('Visible in POS'))
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
                    ->label(__('Standard Codes'))
                    ->placeholder(__('All'))
                    ->trueLabel('Standard only')
                    ->falseLabel('Custom only'),

                TernaryFilter::make('show_in_pos')
                    ->label(__('Visible in POS'))
                    ->placeholder(__('All'))
                    ->trueLabel('Visible in POS only')
                    ->falseLabel('Hidden in POS only'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('showInPos')
                        ->label(__('Show in POS'))
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each->update(['show_in_pos' => true]);
                            Notification::make()
                                ->success()
                                ->title(__('Visible in POS'))
                                ->body("{$records->count()} article group code(s) are now visible in POS.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('hideInPos')
                        ->label(__('Hide in POS'))
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each->update(['show_in_pos' => false]);
                            Notification::make()
                                ->success()
                                ->title(__('Hidden in POS'))
                                ->body("{$records->count()} article group code(s) are now hidden from POS.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
