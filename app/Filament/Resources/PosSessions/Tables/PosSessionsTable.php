<?php

namespace App\Filament\Resources\PosSessions\Tables;

use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\PosSession;
use App\Models\PosEvent;
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
                Action::make('x_report')
                    ->label('X-Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->modalHeading('X-Report (Interim Report)')
                    ->modalContent(fn (PosSession $record) => view('filament.resources.pos-reports.modals.x-report', [
                        'session' => $record,
                        'report' => self::generateXReport($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (PosSession $record): bool => $record->status === 'open'),
                Action::make('z_report')
                    ->label('Z-Report')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->modalHeading('Z-Report (End-of-Day Report)')
                    ->modalContent(fn (PosSession $record) => view('filament.resources.pos-reports.modals.z-report', [
                        'session' => $record,
                        'report' => self::generateZReport($record),
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

    /**
     * Generate X-report data for a session
     */
    protected static function generateXReport(PosSession $session): array
    {
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $charges = $session->charges->where('status', 'succeeded');
        
        // Get settings to check if tips are enabled
        $settings = \App\Models\Setting::getForStore($session->store_id);
        $tipsEnabled = $settings->tips_enabled ?? true;
        
        $totalAmount = $charges->sum('amount');
        $cashAmount = $charges->where('payment_method', 'cash')->sum('amount');
        $cardAmount = $charges->where('payment_method', 'card')->sum('amount');
        $mobileAmount = $charges->where('payment_method', 'mobile')->sum('amount');
        $otherAmount = $totalAmount - $cashAmount - $cardAmount - $mobileAmount;
        $totalTips = $tipsEnabled ? $charges->sum('tip_amount') : 0;
        
        // Calculate VAT (25% standard in Norway)
        $vatRate = 0.25;
        $vatBase = round($totalAmount / (1 + $vatRate), 0);
        $vatAmount = $totalAmount - $vatBase;
        
        // Payment method breakdown
        $byPaymentMethod = $charges->groupBy('payment_method')->map(function ($group) use ($tipsEnabled) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
                'tips' => $tipsEnabled ? $group->sum('tip_amount') : 0,
            ];
        });
        
        // Transaction breakdown by code
        $transactionsByType = $charges->groupBy('transaction_code')->map(function ($group) {
            return [
                'code' => $group->first()->transaction_code,
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        });
        
        // Payment code breakdown
        $byPaymentCode = $charges->groupBy('payment_code')->map(function ($group) {
            return [
                'code' => $group->first()->payment_code,
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        });
        
        // Cash drawer events
        $cashDrawerOpens = $session->events->where('event_code', PosEvent::EVENT_CASH_DRAWER_OPEN)->count();
        $nullinnslagCount = $session->events->where('event_code', PosEvent::EVENT_CASH_DRAWER_OPEN)
            ->where('event_data->nullinnslag', true)->count();
        
        // Receipt count
        $receiptCount = $session->receipts->count();
        
        return [
            'session_id' => $session->id,
            'session_number' => $session->session_number,
            'opened_at' => $session->opened_at,
            'report_generated_at' => now(),
            'store' => [
                'id' => $session->store->id,
                'name' => $session->store->name,
            ],
            'device' => $session->posDevice ? [
                'id' => $session->posDevice->id,
                'name' => $session->posDevice->device_name,
            ] : null,
            'cashier' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->name,
            ] : null,
            'opening_balance' => $session->opening_balance,
            'transactions_count' => $charges->count(),
            'total_amount' => $totalAmount,
            'vat_base' => $vatBase,
            'vat_amount' => $vatAmount,
            'vat_rate' => $vatRate * 100,
            'cash_amount' => $cashAmount,
            'card_amount' => $cardAmount,
            'mobile_amount' => $mobileAmount,
            'other_amount' => $otherAmount,
            'total_tips' => $totalTips,
            'expected_cash' => $session->calculateExpectedCash(),
            'by_payment_method' => $byPaymentMethod,
            'by_payment_code' => $byPaymentCode,
            'transactions_by_type' => $transactionsByType,
            'cash_drawer_opens' => $cashDrawerOpens,
            'nullinnslag_count' => $nullinnslagCount,
            'receipt_count' => $receiptCount,
            'charges' => $charges,
            'tips_enabled' => $tipsEnabled,
        ];
    }

    /**
     * Generate Z-report data for a session
     */
    protected static function generateZReport(PosSession $session): array
    {
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $report = self::generateXReport($session);
        
        // Add Z-report specific data
        $report['closed_at'] = $session->closed_at;
        $report['actual_cash'] = $session->actual_cash;
        $report['cash_difference'] = $session->cash_difference;
        $report['closing_notes'] = $session->closing_notes;
        $report['report_type'] = 'Z-Report';
        
        // Event summary
        $eventSummary = $session->events->groupBy('event_code')->map(function ($group) {
            $firstEvent = $group->first();
            return [
                'code' => $firstEvent->event_code,
                'description' => $firstEvent->description ?? $firstEvent->event_description ?? 'N/A',
                'count' => $group->count(),
            ];
        });
        $report['event_summary'] = $eventSummary;
        
        // Get settings to check if tips are enabled
        $settings = \App\Models\Setting::getForStore($session->store_id);
        $tipsEnabled = $settings->tips_enabled ?? true;
        
        // Complete transaction list with all details
        $report['complete_transaction_list'] = $session->charges->where('status', 'succeeded')->map(function ($charge) use ($tipsEnabled) {
            return [
                'id' => $charge->id,
                'stripe_charge_id' => $charge->stripe_charge_id,
                'amount' => $charge->amount,
                'currency' => $charge->currency,
                'payment_method' => $charge->payment_method,
                'payment_code' => $charge->payment_code,
                'transaction_code' => $charge->transaction_code,
                'tip_amount' => $tipsEnabled ? $charge->tip_amount : 0,
                'description' => $charge->description,
                'paid_at' => $charge->paid_at?->toISOString(),
                'created_at' => $charge->created_at->toISOString(),
            ];
        });
        
        $report['tips_enabled'] = $tipsEnabled;
        
        // Receipt summary
        $receiptSummary = $session->receipts->groupBy('receipt_type')->map(function ($group) {
            return [
                'type' => $group->first()->receipt_type,
                'count' => $group->count(),
            ];
        });
        $report['receipt_summary'] = $receiptSummary;
        
        return $report;
    }
}
