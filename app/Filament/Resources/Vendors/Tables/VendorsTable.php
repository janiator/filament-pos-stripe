<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Models\Vendor;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

                TextColumn::make('supplier_ledger_account_number')
                    ->label('Kontonummer for regnskap')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('commission_percent')
                    ->label(__('Commission'))
                    ->suffix('%')
                    ->sortable()
                    ->placeholder('—')
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
                    BulkAction::make('setCommissionPercent')
                        ->label(__('Set Commission Percentage'))
                        ->icon('heroicon-o-percent-badge')
                        ->color('info')
                        ->form([
                            TextInput::make('commission_percent')
                                ->label(__('Commission Percentage'))
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->required()
                                ->helperText(__('Commission percentage (0-100) applied to vendor sales in X/Z reports.')),
                        ])
                        ->action(function (EloquentCollection|Collection|LazyCollection $records, array $data): void {
                            $commissionPercent = $data['commission_percent'] ?? null;

                            if ($commissionPercent === null || $commissionPercent === '') {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Commission percentage required'))
                                    ->send();

                                return;
                            }

                            $updated = Vendor::query()
                                ->whereIn('id', $records->pluck('id'))
                                ->whereNull('archived_at')
                                ->update(['commission_percent' => $commissionPercent]);

                            Notification::make()
                                ->success()
                                ->title(__('Commission percentage set'))
                                ->body(__('Commission has been updated for :count vendor(s).', ['count' => $updated]))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(__('Commission percentage set')),

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
