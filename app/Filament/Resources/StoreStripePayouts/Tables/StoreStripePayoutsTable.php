<?php

namespace App\Filament\Resources\StoreStripePayouts\Tables;

use App\Enums\AddonType;
use App\Enums\TripletexSyncRunStatus;
use App\Filament\Actions\TripletexPayoutReconciliationAction;
use App\Filament\Actions\TripletexVoucherPreviewAction;
use App\Models\Addon;
use App\Models\StoreStripePayout;
use App\Models\TripletexSyncRun;
use App\Services\Tripletex\TripletexPayoutReconciliationService;
use App\Services\Tripletex\TripletexPayoutSync;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class StoreStripePayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'store.tripletexIntegration',
                'latestTripletexSyncRun',
            ]))
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label(__('filament.resources.store_stripe_payout.columns.amount'))
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('amount', $direction);
                    }),

                TextColumn::make('status')
                    ->label(__('filament.resources.store_stripe_payout.columns.status'))
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'warning' => ['pending', 'in_transit'],
                        'danger' => ['failed', 'canceled'],
                    ])
                    ->sortable(),

                TextColumn::make('tripletex_reconciliation')
                    ->label('TX recon')
                    ->badge()
                    ->visible(fn (): bool => self::isTripletexActivatedForTenant())
                    ->color(fn (?string $state): string => match ($state) {
                        'ok' => 'success',
                        'warn' => 'warning',
                        'fail' => 'danger',
                        default => 'gray',
                    })
                    ->getStateUsing(function (StoreStripePayout $record): ?string {
                        if ($record->status !== 'paid') {
                            return null;
                        }
                        $record->loadMissing('store.tripletexIntegration');
                        $integration = $record->store?->tripletexIntegration;
                        if (! $integration?->isConnected()) {
                            return null;
                        }

                        return app(TripletexPayoutReconciliationService::class)
                            ->reconcile($record, $integration)['status'] ?? null;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('tripletex_synced')
                    ->label('TX')
                    ->tooltip('Tripletex payout voucher status')
                    ->visible(fn (): bool => self::isTripletexActivatedForTenant())
                    ->getStateUsing(function (StoreStripePayout $record): ?string {
                        if ($record->status !== 'paid') {
                            return null;
                        }

                        $integration = $record->store?->tripletexIntegration;
                        if (! $integration?->isConnected()) {
                            return null;
                        }

                        $runStatus = $record->latestTripletexSyncRun?->status;
                        if ($runStatus === TripletexSyncRunStatus::Success) {
                            return 'success';
                        }
                        if ($runStatus === TripletexSyncRunStatus::Failed) {
                            return 'failed';
                        }
                        if ($runStatus === TripletexSyncRunStatus::Skipped) {
                            return 'skipped';
                        }

                        return 'not_synced';
                    })
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'success' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        'skipped' => 'heroicon-o-information-circle',
                        'not_synced' => 'heroicon-o-minus-circle',
                        default => null,
                    })
                    ->color(fn (?string $state): ?string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        'not_synced' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('arrival_date')
                    ->label(__('filament.resources.store_stripe_payout.columns.arrival_date'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('method')
                    ->label(__('filament.resources.store_stripe_payout.columns.method'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('store.name')
                    ->label(__('filament.resources.store_stripe_payout.columns.store'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_payout_id')
                    ->label(__('filament.resources.store_stripe_payout.columns.payout_id'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament.resources.store_stripe_payout.columns.synced'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.resources.store_stripe_payout.columns.status'))
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'in_transit' => 'In transit',
                        'failed' => 'Failed',
                        'canceled' => 'Canceled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                TripletexPayoutReconciliationAction::makeTableAction(),
                TripletexVoucherPreviewAction::makeTableActionForPayout(),
                Action::make('sync_tripletex')
                    ->label('Sync Tripletex')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('gray')
                    ->visible(fn (StoreStripePayout $record): bool => self::canSyncPayoutToTripletex($record))
                    ->action(function (StoreStripePayout $record): void {
                        Log::info('Filament Tripletex payout sync clicked', [
                            'store_stripe_payout_id' => $record->id,
                            'stripe_payout_id' => $record->stripe_payout_id,
                        ]);

                        Notification::make()
                            ->title('Syncing payout to Tripletex...')
                            ->body($record->stripe_payout_id)
                            ->info()
                            ->send();

                        try {
                            $sync = app(TripletexPayoutSync::class);
                            $ok = $sync->sync($record->id, true);
                            $run = TripletexSyncRun::query()
                                ->where('store_stripe_payout_id', $record->id)
                                ->latest('id')
                                ->first();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Tripletex payout sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($run?->status === TripletexSyncRunStatus::Skipped) {
                            Notification::make()
                                ->title('Tripletex payout sync skipped')
                                ->body($run->error_message ?? 'No voucher was posted.')
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        if (! $ok || $run?->status !== TripletexSyncRunStatus::Success) {
                            Notification::make()
                                ->title('Tripletex payout sync failed')
                                ->body($run?->error_message ?? 'See Tripletex sync history for details.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Synced payout to Tripletex')
                            ->body($run->tripletex_voucher_id ? "Voucher #{$run->tripletex_voucher_id}" : 'Payout voucher posted.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->defaultSort('arrival_date', 'desc')
            ->emptyStateHeading(__('filament.resources.store_stripe_payout.empty_heading'))
            ->emptyStateDescription(__('filament.resources.store_stripe_payout.empty_description'));
    }

    protected static function isTripletexActivatedForTenant(): bool
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return false;
        }

        return Addon::storeHasActiveAddon($tenant->getKey(), AddonType::Tripletex);
    }

    protected static function canSyncPayoutToTripletex(StoreStripePayout $record): bool
    {
        if ($record->status !== 'paid') {
            return false;
        }

        $tenant = Filament::getTenant();
        if (! $tenant || ! Addon::storeHasActiveAddon($tenant->getKey(), AddonType::Tripletex)) {
            return false;
        }

        if ((int) $record->store_id !== (int) $tenant->getKey()) {
            return false;
        }

        $record->loadMissing('store.tripletexIntegration');
        $integration = $record->store?->tripletexIntegration;
        if (! $integration?->isConnected() || ! $integration->sync_enabled) {
            return false;
        }

        return true;
    }
}
