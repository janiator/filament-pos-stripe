<?php

namespace App\Filament\Resources\PosSessions\Tables;

use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\PosSession;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PosSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_number')
                    ->label('Session #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),
                TextColumn::make('posDevice.device_name')
                    ->label('Device')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable(),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Closed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('transaction_count')
                    ->label('Transactions')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('nok', divideBy: 100)
                    ->sortable(),
                TextColumn::make('cash_difference')
                    ->label('Cash Diff')
                    ->money('nok', divideBy: 100)
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordUrl(fn ($record) => PosSessionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (PosSession $record): bool => $record->status !== 'closed'),
                Action::make('close')
                    ->label('Close Session')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Close POS Session')
                    ->modalDescription(fn (PosSession $record): string => "Are you sure you want to close session {$record->session_number}? This will calculate expected cash and mark the session as closed.")
                    ->form([
                        \Filament\Forms\Components\TextInput::make('actual_cash')
                            ->label('Actual Cash')
                            ->numeric()
                            ->suffix('kr')
                            ->step(0.01)
                            ->helperText('Enter the actual cash count at closing in NOK. Leave empty to use expected cash.'),
                        \Filament\Forms\Components\Textarea::make('closing_notes')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // No bulk actions for sessions
                ]),
            ]);
    }
}
