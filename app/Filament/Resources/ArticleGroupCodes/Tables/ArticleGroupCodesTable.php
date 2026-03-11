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
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('default_vat_percent')
                    ->label('Default VAT %')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state * 100, 2) : '—')
                    ->suffix('%')
                    ->sortable()
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

                IconColumn::make('show_in_pos')
                    ->label('Visible in POS')
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
                    ->label('Standard Codes')
                    ->placeholder('All')
                    ->trueLabel('Standard only')
                    ->falseLabel('Custom only'),

                TernaryFilter::make('show_in_pos')
                    ->label('Visible in POS')
                    ->placeholder('All')
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
                        ->label('Show in POS')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->each->update(['show_in_pos' => true]);
                            Notification::make()
                                ->success()
                                ->title('Visible in POS')
                                ->body("{$records->count()} article group code(s) are now visible in POS.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('hideInPos')
                        ->label('Hide in POS')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->action(function (Collection $records): void {
                            $records->each->update(['show_in_pos' => false]);
                            Notification::make()
                                ->success()
                                ->title('Hidden in POS')
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
