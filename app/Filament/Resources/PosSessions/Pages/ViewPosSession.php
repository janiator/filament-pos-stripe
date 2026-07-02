<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Actions\PosSessions\RegenerateZReports;
use App\Enums\AddonType;
use App\Enums\PowerOfficeSyncRunStatus;
use App\Enums\TripletexSyncRunStatus;
use App\Filament\Actions\TripletexVoucherPreviewAction;
use App\Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\Addon;
use App\Models\PosEvent;
use App\Models\PowerOfficeSyncRun;
use App\Models\TripletexSyncRun;
use App\Services\CashDrawerService;
use App\Services\PowerOffice\PowerOfficeZReportSync;
use App\Services\Tripletex\TripletexSyncPreviewService;
use App\Services\Tripletex\TripletexZReportSync;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
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
            Action::make('sync_poweroffice')
                ->label(__('Sync PowerOffice'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->visible(fn (): bool => $this->canSyncToPowerOffice())
                ->requiresConfirmation(fn (): bool => $this->latestSuccessfulPowerOfficeRun() !== null)
                ->modalHeading(__('Re-sync PowerOffice'))
                ->modalDescription(function (): string {
                    $run = $this->latestSuccessfulPowerOfficeRun();
                    $voucherLabel = $run?->journal_voucher_no ? "bilagsnr #{$run->journal_voucher_no}" : 'the existing voucher';

                    return __('This session was already synced. The :voucher will be reversed in PowerOffice and a new voucher will be posted from the current Z-report.', ['voucher' => $voucherLabel]);
                })
                ->action(function (): void {
                    Notification::make()
                        ->title(__('Syncing with PowerOffice...'))
                        ->body("Session {$this->record->session_number}")
                        ->info()
                        ->send();

                    try {
                        $sync = app(PowerOfficeZReportSync::class);
                        $ok = $sync->sync($this->record->id, force: true, reverseExisting: true);
                        $run = PowerOfficeSyncRun::query()
                            ->where('pos_session_id', $this->record->id)
                            ->latest('id')
                            ->first();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('PowerOffice sync failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    if (! $ok || $run?->status !== PowerOfficeSyncRunStatus::Success) {
                        Notification::make()
                            ->title(__('PowerOffice sync failed'))
                            ->body($run?->error_message ?? 'See PowerOffice sync runs for details.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $journalNo = $run->journal_voucher_no
                        ?? data_get($run->response_payload, 'VoucherNo')
                        ?? data_get($run->response_payload, 'voucherNo');

                    Notification::make()
                        ->title(__('Synced to PowerOffice'))
                        ->body((is_numeric($journalNo) && (int) $journalNo > 0) ? "Bilagsnr #{$journalNo}" : 'Z-report synced successfully.')
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Action::make('preview_tripletex_voucher')
                ->label(__('Preview Tripletex voucher'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => TripletexVoucherPreviewAction::canPreviewZReport($this->record))
                ->slideOver()
                ->modalHeading(__('Tripletex voucher preview'))
                ->modalDescription(__('Ledger lines for this session’s Z-report. Turn on account resolution to call Tripletex and include the exact JSON for POST /ledger/voucher.'))
                ->modalWidth('4xl')
                ->fillForm(fn (): array => [
                    'resolve_tripletex_accounts' => false,
                    'preview_json' => json_encode(
                        $this->tripletexZReportVoucherPreviewPayload(false),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
                ])
                ->form([
                    Toggle::make('resolve_tripletex_accounts')
                        ->label(__('Resolve Tripletex account IDs (calls Tripletex API)'))
                        ->helperText(__('Creates a short-lived session token and resolves each ledger account number used in the voucher.'))
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $payload = $this->tripletexZReportVoucherPreviewPayload((bool) $state);
                            $set('preview_json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        }),
                    Textarea::make('preview_json')
                        ->label(__('Preview JSON'))
                        ->rows(28)
                        ->readOnly()
                        ->columnSpanFull()
                        ->extraInputAttributes(['class' => 'font-mono text-xs']),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
            Action::make('sync_tripletex')
                ->label(__('Sync Tripletex'))
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->visible(fn (): bool => $this->canSyncToTripletex())
                ->action(function (): void {
                    Notification::make()
                        ->title(__('Syncing with Tripletex...'))
                        ->body("Session {$this->record->session_number}")
                        ->info()
                        ->send();

                    try {
                        $sync = app(TripletexZReportSync::class);
                        $ok = $sync->sync($this->record->id, true);
                        $run = TripletexSyncRun::query()
                            ->where('pos_session_id', $this->record->id)
                            ->latest('id')
                            ->first();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('Tripletex sync failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($run?->status === TripletexSyncRunStatus::Skipped) {
                        Notification::make()
                            ->title(__('Tripletex sync skipped'))
                            ->body($run->error_message ?? 'No voucher was posted.')
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    if (! $ok || $run?->status !== TripletexSyncRunStatus::Success) {
                        Notification::make()
                            ->title(__('Tripletex sync failed'))
                            ->body($run?->error_message ?? 'See Tripletex sync history for details.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('Synced to Tripletex'))
                        ->body($run->tripletex_voucher_id ? "Voucher #{$run->tripletex_voucher_id}" : 'Z-report synced successfully.')
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Action::make('cash_withdrawal')
                ->label(__('Registrer kontantuttak'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('warning')
                ->modalHeading(__('Registrer kontantuttak'))
                ->modalDescription(__('Registrer at penger tas ut av kassen. Beløpet vises i X- og Z-rapport.'))
                ->form([
                    TextInput::make('amount')
                        ->label(__('Beløp (NOK)'))
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required()
                        ->suffix(__('NOK')),
                    Textarea::make('reason')
                        ->label(__('Årsak (valgfritt)'))
                        ->maxLength(500)
                        ->rows(2),
                ])
                ->visible(fn () => $this->record->status === 'open')
                ->action(function (array $data): void {
                    $amountOre = (int) round((float) $data['amount'] * 100);
                    if ($amountOre < 1) {
                        Notification::make()
                            ->title(__('Ugyldig beløp'))
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
                        ->title(__('Kontantuttak registrert'))
                        ->body(number_format($data['amount'], 2, ',', ' ').' NOK registrert.')
                        ->success()
                        ->send();
                    $this->refresh();
                }),
            Action::make('cash_deposit')
                ->label(__('Registrer kontantinnskudd'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->modalHeading(__('Registrer kontantinnskudd'))
                ->modalDescription(__('Registrer at penger settes inn i kassen. Beløpet vises i X- og Z-rapport.'))
                ->form([
                    TextInput::make('amount')
                        ->label(__('Beløp (NOK)'))
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required()
                        ->suffix(__('NOK')),
                    Textarea::make('reason')
                        ->label(__('Årsak (valgfritt)'))
                        ->maxLength(500)
                        ->rows(2),
                ])
                ->visible(fn () => $this->record->status === 'open')
                ->action(function (array $data): void {
                    $amountOre = (int) round((float) $data['amount'] * 100);
                    if ($amountOre < 1) {
                        Notification::make()
                            ->title(__('Ugyldig beløp'))
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
                        ->title(__('Kontantinnskudd registrert'))
                        ->body(number_format($data['amount'], 2, ',', ' ').' NOK registrert.')
                        ->success()
                        ->send();
                    $this->refresh();
                }),
            Action::make('regenerate_z_report')
                ->label(__('Regenerate Z-Report'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Regenerate Z-Report'))
                ->modalDescription(fn (): string => "This will regenerate the Z-report for session {$this->record->session_number} and attempt to find any missing data (charges, receipts, events) that may not have been properly linked.")
                ->form([
                    Toggle::make('find_missing_data')
                        ->label(__('Find Missing Data'))
                        ->helperText(__('Attempt to find and link missing charges, receipts, and events'))
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

                    $tenant = Filament::getTenant();

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
                            ->title(__('Error Regenerating Z-Report'))
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
                        ->title(__('Z-Report Regenerated'))
                        ->body($message)
                        ->success()
                        ->persistent()
                        ->send();

                    // Log Z-report event (13009) per § 2-8-3
                    PosEvent::create([
                        'store_id' => $this->record->effectiveStoreId(),
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

    protected function latestSuccessfulPowerOfficeRun(): ?PowerOfficeSyncRun
    {
        return PowerOfficeSyncRun::query()
            ->where('pos_session_id', $this->record->id)
            ->where('status', PowerOfficeSyncRunStatus::Success)
            ->latest('id')
            ->first();
    }

    protected function canSyncToPowerOffice(): bool
    {
        if ($this->record->status !== 'closed') {
            return false;
        }

        $tenant = Filament::getTenant();
        if (! $tenant || ! Addon::storeHasActiveAddon($tenant->getKey(), AddonType::PowerOfficeGo)) {
            return false;
        }

        $this->record->loadMissing('store.powerOfficeIntegration');
        $integration = $this->record->store?->powerOfficeIntegration;
        if (! $integration?->isConnected() || ! $integration->sync_enabled) {
            return false;
        }

        return app(PowerOfficeZReportSync::class)->isSessionEligibleForSync($this->record);
    }

    protected function canSyncToTripletex(): bool
    {
        if ($this->record->status !== 'closed') {
            return false;
        }

        $tenant = Filament::getTenant();
        if (! $tenant || ! Addon::storeHasActiveAddon($tenant->getKey(), AddonType::Tripletex)) {
            return false;
        }

        $this->record->loadMissing('store.tripletexIntegration');
        $integration = $this->record->store?->tripletexIntegration;
        if (! $integration?->isConnected() || ! $integration->sync_enabled) {
            return false;
        }

        return app(TripletexZReportSync::class)->isSessionEligibleForSync($this->record);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tripletexZReportVoucherPreviewPayload(bool $resolveTripletexAccounts): array
    {
        $this->record->loadMissing('store.tripletexIntegration');
        $integration = $this->record->store?->tripletexIntegration;
        if (! $integration) {
            return ['ok' => false, 'error' => 'Tripletex integration is not configured for this store.'];
        }

        return app(TripletexSyncPreviewService::class)->previewZReport($this->record, $integration, $resolveTripletexAccounts);
    }
}
