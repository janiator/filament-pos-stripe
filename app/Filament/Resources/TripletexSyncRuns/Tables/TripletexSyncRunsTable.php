<?php

namespace App\Filament\Resources\TripletexSyncRuns\Tables;

use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Jobs\SyncTripletexPayoutJob;
use App\Jobs\SyncTripletexZReportJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TripletexSyncRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('sync_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (TripletexSyncType $state): string => $state->label()),
                TextColumn::make('pos_session_id')
                    ->label(__('Session'))
                    ->placeholder(__('—'))
                    ->sortable(),
                TextColumn::make('store_stripe_payout_id')
                    ->label(__('Payout row'))
                    ->placeholder(__('—'))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TripletexSyncRunStatus $state): string => $state->label())
                    ->color(fn (TripletexSyncRunStatus $state): string => match ($state) {
                        TripletexSyncRunStatus::Success => 'success',
                        TripletexSyncRunStatus::Failed => 'danger',
                        TripletexSyncRunStatus::Processing => 'warning',
                        TripletexSyncRunStatus::Skipped => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('tripletex_voucher_id')
                    ->label(__('Voucher'))
                    ->placeholder(__('—')),
                TextColumn::make('attempts'),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->placeholder(__('—')),
                TextColumn::make('error_message')
                    ->limit(40)
                    ->placeholder(__('—'))
                    ->tooltip(fn ($state): ?string => is_string($state) ? $state : null),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('retry')
                    ->label(__('Retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record): bool => in_array($record->status, [
                        TripletexSyncRunStatus::Failed,
                        TripletexSyncRunStatus::Skipped,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        if ($record->sync_type === TripletexSyncType::ZReport && $record->pos_session_id) {
                            SyncTripletexZReportJob::dispatch($record->pos_session_id, true);
                            Notification::make()
                                ->title(__('Tripletex Z-report retry queued'))
                                ->success()
                                ->send();

                            return;
                        }

                        if ($record->sync_type === TripletexSyncType::Payout && $record->store_stripe_payout_id) {
                            $record->loadMissing('integration');
                            $skipBank = (bool) ($record->integration?->skip_payout_bank_transfer ?? false);
                            SyncTripletexPayoutJob::dispatch($record->store_stripe_payout_id, true, $skipBank);
                            Notification::make()
                                ->title(__('Tripletex payout retry queued'))
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(__('Cannot retry this run'))
                            ->body('Missing session or payout reference.')
                            ->warning()
                            ->send();
                    }),
            ]);
    }
}
