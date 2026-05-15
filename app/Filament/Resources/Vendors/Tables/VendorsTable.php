<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Models\Vendor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

class VendorsTable
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

                TextColumn::make('description')
                    ->label(__('Description'))
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('contact_email')
                    ->label(__('Email'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('contact_phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('products_count')
                    ->label(__('Products'))
                    ->counts('products')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                IconColumn::make('active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('archived_at')
                    ->label(__('Archived'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('archive_status')
                    ->label(__('Archive'))
                    ->form([
                        Select::make('value')
                            ->label(__('Show'))
                            ->options([
                                'active' => __('Not archived'),
                                'archived' => __('Archived only'),
                                'all' => __('All'),
                            ])
                            ->default('active'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? 'active';

                        return match ($value) {
                            'archived' => $query->whereNotNull('vendors.archived_at'),
                            'all' => $query,
                            default => $query->whereNull('vendors.archived_at'),
                        };
                    })
                    ->default(['value' => 'active']),
                TernaryFilter::make('active')
                    ->label(__('Active'))
                    ->placeholder(__('All'))
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (Vendor $record): bool => $record->isArchived()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records): void {
                            if (! $action->shouldFetchSelectedRecords()) {
                                try {
                                    $count = $action->getSelectedRecordsQuery()
                                        ->whereNull('archived_at')
                                        ->update(['archived_at' => now()]);
                                    $action->reportBulkProcessingSuccessfulRecordsCount($count);
                                } catch (Throwable $exception) {
                                    $action->reportCompleteBulkProcessingFailure();

                                    report($exception);
                                }

                                return;
                            }

                            $isFirstException = true;

                            $records->each(static function (Model $record) use ($action, &$isFirstException): void {
                                try {
                                    if (! $record instanceof Vendor) {
                                        return;
                                    }

                                    $record->archive() || $action->reportBulkProcessingFailure();
                                } catch (Throwable $exception) {
                                    $action->reportBulkProcessingFailure();

                                    if ($isFirstException) {
                                        report($exception);

                                        $isFirstException = false;
                                    }
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
