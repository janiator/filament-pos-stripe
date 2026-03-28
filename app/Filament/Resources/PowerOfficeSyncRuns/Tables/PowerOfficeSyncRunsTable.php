<?php

namespace App\Filament\Resources\PowerOfficeSyncRuns\Tables;

use App\Enums\PowerOfficeSyncRunStatus;
use App\Jobs\SyncPowerOfficeZReportJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PowerOfficeSyncRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('pos_session_id')
                    ->label('Session')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PowerOfficeSyncRunStatus $state): string => $state->label())
                    ->color(fn (PowerOfficeSyncRunStatus $state): string => match ($state) {
                        PowerOfficeSyncRunStatus::Success => 'success',
                        PowerOfficeSyncRunStatus::Failed => 'danger',
                        PowerOfficeSyncRunStatus::Processing => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('journal_voucher_no')
                    ->label('Journal #')
                    ->formatStateUsing(fn ($state): ?string => (is_numeric($state) && (int) $state > 0) ? (string) $state : null)
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
                    ->visible(fn ($record): bool => $record->status === PowerOfficeSyncRunStatus::Failed)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        SyncPowerOfficeZReportJob::dispatch($record->pos_session_id, true);
                        Notification::make()
                            ->title('Retry queued')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
