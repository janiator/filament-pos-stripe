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
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (TripletexSyncType $state): string => $state->label()),
                TextColumn::make('pos_session_id')
                    ->label('Session')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('store_stripe_payout_id')
                    ->label('Payout row')
                    ->placeholder('—')
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
                    ->label('Voucher')
                    ->placeholder('—'),
                TextColumn::make('attempts'),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('error_message')
                    ->limit(40)
                    ->placeholder('—')
                    ->tooltip(fn ($state): ?string => is_string($state) ? $state : null),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
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
                                ->title('Tripletex Z-report retry queued')
                                ->success()
                                ->send();

                            return;
                        }

                        if ($record->sync_type === TripletexSyncType::Payout && $record->store_stripe_payout_id) {
                            SyncTripletexPayoutJob::dispatch($record->store_stripe_payout_id, true);
                            Notification::make()
                                ->title('Tripletex payout retry queued')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Cannot retry this run')
                            ->body('Missing session or payout reference.')
                            ->warning()
                            ->send();
                    }),
            ]);
    }
}
