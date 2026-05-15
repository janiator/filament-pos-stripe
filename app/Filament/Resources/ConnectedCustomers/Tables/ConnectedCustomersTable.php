<?php

namespace App\Filament\Resources\ConnectedCustomers\Tables;

use App\Models\ConnectedCustomer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;

class ConnectedCustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder(__('-')),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable()
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedEnvelope)
                    ->placeholder(__('-')),

                TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->url(fn (ConnectedCustomer $record) => $record->store
                        ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                        : null),

                TextColumn::make('subscriptions_count')
                    ->label(__('Subscriptions'))
                    ->counts('subscriptions')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('archived_at')
                    ->label(__('Archived'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('stripe_customer_id')
                    ->label(__('Customer ID'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_account_id')
                    ->label(__('Account ID'))
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
                            'archived' => $query->whereNotNull(
                                $query->getModel()->qualifyColumn('archived_at')
                            ),
                            'all' => $query,
                            default => $query->whereNull(
                                $query->getModel()->qualifyColumn('archived_at')
                            ),
                        };
                    })
                    ->default(['value' => 'active']),
                SelectFilter::make('stripe_account_id')
                    ->label(__('Store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn (ConnectedCustomer $record): bool => $record->isArchived()),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('Archive'))
                        ->modalHeading(__('Archive selected customers?'))
                        ->modalDescription(__('They will be hidden from the POS customer list. Purchase history is kept.'))
                        ->modalSubmitActionLabel(__('Archive'))
                        ->using(function (DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records): void {
                            if (! $action->shouldFetchSelectedRecords()) {
                                try {
                                    $count = $action->getSelectedRecordsQuery()
                                        ->whereNull('stripe_connected_customer_mappings.archived_at')
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
                                    if (! $record instanceof ConnectedCustomer) {
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
            ]);
    }
}
