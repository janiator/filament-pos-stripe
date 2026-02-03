<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Actions\PosSessions\RegenerateZReports;
use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\PosEvent;
use App\Services\CashDrawerService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPosSession extends ViewRecord
{
    protected static string $resource = PosSessionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Eager load relationships for the infolist
        $this->record->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => $this->record->status !== 'closed'),
            Action::make('cash_withdrawal')
                ->label('Registrer kontantuttak')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('warning')
                ->modalHeading('Registrer kontantuttak')
                ->modalDescription('Registrer at penger tas ut av kassen. Beløpet vises i X- og Z-rapport.')
                ->form([
                    TextInput::make('amount')
                        ->label('Beløp (NOK)')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required()
                        ->suffix('NOK'),
                    Textarea::make('reason')
                        ->label('Årsak (valgfritt)')
                        ->maxLength(500)
                        ->rows(2),
                ])
                ->visible(fn () => $this->record->status === 'open')
                ->action(function (array $data): void {
                    $amountOre = (int) round((float) $data['amount'] * 100);
                    if ($amountOre < 1) {
                        Notification::make()
                            ->title('Ugyldig beløp')
                            ->body('Beløpet må være minst 0,01 NOK.')
                            ->danger()
                            ->send();

                        return;
                    }
                    app(CashDrawerService::class)->logWithdrawal(
                        $this->record,
                        $amountOre,
                        $data['reason'] ?? null
                    );
                    Notification::make()
                        ->title('Kontantuttak registrert')
                        ->body(number_format($data['amount'], 2, ',', ' ').' NOK registrert.')
                        ->success()
                        ->send();
                    $this->refresh();
                }),
            Action::make('cash_deposit')
                ->label('Registrer kontantinnskudd')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->modalHeading('Registrer kontantinnskudd')
                ->modalDescription('Registrer at penger settes inn i kassen. Beløpet vises i X- og Z-rapport.')
                ->form([
                    TextInput::make('amount')
                        ->label('Beløp (NOK)')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required()
                        ->suffix('NOK'),
                    Textarea::make('reason')
                        ->label('Årsak (valgfritt)')
                        ->maxLength(500)
                        ->rows(2),
                ])
                ->visible(fn () => $this->record->status === 'open')
                ->action(function (array $data): void {
                    $amountOre = (int) round((float) $data['amount'] * 100);
                    if ($amountOre < 1) {
                        Notification::make()
                            ->title('Ugyldig beløp')
                            ->body('Beløpet må være minst 0,01 NOK.')
                            ->danger()
                            ->send();

                        return;
                    }
                    app(CashDrawerService::class)->logDeposit(
                        $this->record,
                        $amountOre,
                        $data['reason'] ?? null
                    );
                    Notification::make()
                        ->title('Kontantinnskudd registrert')
                        ->body(number_format($data['amount'], 2, ',', ' ').' NOK registrert.')
                        ->success()
                        ->send();
                    $this->refresh();
                }),
            Action::make('regenerate_z_report')
                ->label('Regenerate Z-Report')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Z-Report')
                ->modalDescription(fn (): string => "This will regenerate the Z-report for session {$this->record->session_number} and attempt to find any missing data (charges, receipts, events) that may not have been properly linked.")
                ->form([
                    Toggle::make('find_missing_data')
                        ->label('Find Missing Data')
                        ->helperText('Attempt to find and link missing charges, receipts, and events')
                        ->default(true),
                ])
                ->visible(function () {
                    if ($this->record->status !== 'closed') {
                        return false;
                    }

                    // Only show to super admins
                    $user = auth()->user();
                    if (! $user) {
                        return false;
                    }

                    $tenant = \Filament\Facades\Filament::getTenant();

                    return $tenant
                        ? $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists()
                        : $user->hasRole('super_admin');
                })
                ->action(function (array $data) {
                    $action = new RegenerateZReports;
                    $findMissingData = $data['find_missing_data'] ?? true;

                    // Get original report data for comparison
                    $originalReport = $this->record->closing_data['z_report_data'] ?? null;
                    $originalTransactionCount = $originalReport['transactions_count'] ?? null;
                    $originalTotalAmount = $originalReport['total_amount'] ?? null;

                    $stats = $action->regenerateSingle($this->record, $findMissingData);

                    if (! $stats['success']) {
                        Notification::make()
                            ->title('Error Regenerating Z-Report')
                            ->body("Failed to regenerate Z-report: {$stats['error']}")
                            ->danger()
                            ->send();

                        return;
                    }

                    // Refresh to get updated closing_data
                    $this->record->refresh();
                    $regenerationChanges = $this->record->closing_data['z_report_regeneration_changes'] ?? [];

                    // Show success notification with found data info and changes
                    $message = "Z-report regenerated successfully for session {$this->record->session_number}.\n\n";

                    if ($stats['charges_found'] > 0 || $stats['receipts_found'] > 0 || $stats['events_found'] > 0) {
                        $message .= "Found: {$stats['charges_found']} charges, {$stats['receipts_found']} receipts, {$stats['events_found']} events\n\n";
                    }

                    // Show value changes if any
                    if ($originalReport) {
                        $newTransactionCount = $regenerationChanges['transaction_count_after'] ?? null;
                        $newTotalAmount = $regenerationChanges['total_amount_after'] ?? null;

                        $hasChanges = false;
                        if ($originalTransactionCount !== null && $newTransactionCount !== null && $originalTransactionCount != $newTransactionCount) {
                            $message .= "Transactions: {$originalTransactionCount} → {$newTransactionCount}\n";
                            $hasChanges = true;
                        }
                        if ($originalTotalAmount !== null && $newTotalAmount !== null && $originalTotalAmount != $newTotalAmount) {
                            $originalAmountNok = number_format($originalTotalAmount / 100, 2);
                            $newAmountNok = number_format($newTotalAmount / 100, 2);
                            $message .= "Total Amount: {$originalAmountNok} NOK → {$newAmountNok} NOK\n";
                            $hasChanges = true;
                        }

                        if ($hasChanges) {
                            $message .= "\nNote: Values changed due to new data found or recalculated vendor commissions/settings.";
                        } else {
                            $message .= 'No value changes detected.';
                        }
                    } else {
                        $message .= 'No previous report to compare.';
                    }

                    Notification::make()
                        ->title('Z-Report Regenerated')
                        ->body($message)
                        ->success()
                        ->persistent()
                        ->send();

                    // Log Z-report event (13009) per § 2-8-3
                    PosEvent::create([
                        'store_id' => $this->record->store_id,
                        'pos_device_id' => $this->record->pos_device_id,
                        'pos_session_id' => $this->record->id,
                        'user_id' => auth()->id(),
                        'event_code' => PosEvent::EVENT_Z_REPORT,
                        'event_type' => 'report',
                        'description' => "Z-report regenerated for session {$this->record->session_number}",
                        'event_data' => [
                            'report_type' => 'Z-Report',
                            'session_number' => $this->record->session_number,
                            'regenerated' => true,
                            'changes' => $regenerationChanges,
                        ],
                        'occurred_at' => now(),
                    ]);

                    $this->refresh();
                }),
        ];
    }
}
