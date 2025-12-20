<?php

namespace App\Filament\Resources\PosDevices\RelationManagers;

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PosSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'posSessions';

    protected static ?string $title = 'POS Sessions (X/Z Reports)';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('session_number')
            ->columns([
                Tables\Columns\TextColumn::make('session_number')
                    ->label('Session #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Closed')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_count')
                    ->label('Transactions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('nok', divideBy: 100)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                Action::make('x_report')
                    ->label('X-Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->modalHeading('X-Report (Interim Report)')
                    ->before(function (PosSession $record) {
                        // Generate report data first
                        $report = PosSessionsTable::generateXReport($record);
                        
                        // Log X-report event (13008) per § 2-8-2
                        \App\Models\PosEvent::create([
                            'store_id' => $record->store_id,
                            'pos_device_id' => $record->pos_device_id,
                            'pos_session_id' => $record->id,
                            'user_id' => auth()->id(),
                            'event_code' => \App\Models\PosEvent::EVENT_X_REPORT,
                            'event_type' => 'report',
                            'description' => "X-report for session {$record->session_number}",
                            'event_data' => [
                                'report_type' => 'X-Report',
                                'session_number' => $record->session_number,
                                'report_data' => $report,
                            ],
                            'occurred_at' => now(),
                        ]);
                    })
                    ->modalContent(fn (PosSession $record) => view('filament.resources.pos-reports.modals.x-report', [
                        'session' => $record,
                        'report' => PosSessionsTable::generateXReport($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (PosSession $record): bool => $record->status === 'open'),
                Action::make('z_report')
                    ->label('Z-Report')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->modalHeading('Z-Report (End-of-Day Report)')
                    ->before(function (PosSession $record) {
                        // Generate report data first
                        $report = PosSessionsTable::generateZReport($record);
                        
                        // Log Z-report event (13009) per § 2-8-3
                        \App\Models\PosEvent::create([
                            'store_id' => $record->store_id,
                            'pos_device_id' => $record->pos_device_id,
                            'pos_session_id' => $record->id,
                            'user_id' => auth()->id(),
                            'event_code' => \App\Models\PosEvent::EVENT_Z_REPORT,
                            'event_type' => 'report',
                            'description' => "Z-report for session {$record->session_number}",
                            'event_data' => [
                                'report_type' => 'Z-Report',
                                'session_number' => $record->session_number,
                                'report_data' => $report,
                            ],
                            'occurred_at' => now(),
                        ]);
                    })
                    ->modalContent(fn (PosSession $record) => view('filament.resources.pos-reports.modals.z-report', [
                        'session' => $record,
                        'report' => PosSessionsTable::generateZReport($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (PosSession $record): bool => $record->status === 'closed'),
                Action::make('close')
                    ->label('Close Session')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Close POS Session')
                    ->modalDescription(fn (PosSession $record): string => "Are you sure you want to close session {$record->session_number}? This will calculate expected cash and mark the session as closed.")
                    ->form([
                        Forms\Components\TextInput::make('actual_cash')
                            ->label('Actual Cash')
                            ->numeric()
                            ->suffix('kr')
                            ->step(0.01)
                            ->helperText('Enter the actual cash count at closing in NOK. Leave empty to use expected cash.'),
                        Forms\Components\Textarea::make('closing_notes')
                            ->label('Closing Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Optional notes about the session closing.'),
                    ])
                    ->visible(fn (PosSession $record): bool => $record->status === 'open')
                    ->action(function (PosSession $record, array $data): void {
                        if (!$record->canBeClosed()) {
                            Notification::make()
                                ->title('Cannot close session')
                                ->danger()
                                ->body('This session cannot be closed.')
                                ->send();
                            return;
                        }

                        // Convert from NOK to øre (multiply by 100)
                        $actualCash = isset($data['actual_cash']) && $data['actual_cash'] !== '' && $data['actual_cash'] !== null
                            ? (int) round((float) $data['actual_cash'] * 100)
                            : null;

                        $success = $record->close($actualCash, $data['closing_notes'] ?? null);

                        if ($success) {
                            Notification::make()
                                ->title('Session closed')
                                ->success()
                                ->body("Session {$record->session_number} has been closed successfully.")
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to close session')
                                ->danger()
                                ->body('An error occurred while closing the session.')
                                ->send();
                        }
                    }),
            ]);
    }
}

