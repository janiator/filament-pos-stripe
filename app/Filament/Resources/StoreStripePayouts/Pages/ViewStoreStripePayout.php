<?php

namespace App\Filament\Resources\StoreStripePayouts\Pages;

use App\Enums\AddonType;
use App\Enums\TripletexSyncRunStatus;
use App\Filament\Actions\TripletexVoucherPreviewAction;
use App\Filament\Resources\StoreStripePayouts\StoreStripePayoutResource;
use App\Models\Addon;
use App\Models\TripletexSyncRun;
use App\Services\Tripletex\TripletexPayoutSync;
use App\Services\Tripletex\TripletexSyncPreviewService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewStoreStripePayout extends ViewRecord
{
    protected static string $resource = StoreStripePayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview_tripletex_voucher')
                ->label('Preview Tripletex voucher')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => TripletexVoucherPreviewAction::canPreviewPayout($this->record))
                ->slideOver()
                ->modalHeading('Tripletex voucher preview')
                ->modalDescription('Ledger lines for this Stripe payout. Turn on account resolution to call Tripletex and include the exact JSON for POST /ledger/voucher.')
                ->modalWidth('4xl')
                ->fillForm(fn (): array => [
                    'resolve_tripletex_accounts' => false,
                    'preview_json' => json_encode(
                        $this->tripletexPayoutVoucherPreviewPayload(false),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
                ])
                ->form([
                    Toggle::make('resolve_tripletex_accounts')
                        ->label('Resolve Tripletex account IDs (calls Tripletex API)')
                        ->helperText('Creates a short-lived session token and resolves each ledger account number used in the voucher.')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $payload = $this->tripletexPayoutVoucherPreviewPayload((bool) $state);
                            $set('preview_json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        }),
                    Textarea::make('preview_json')
                        ->label('Preview JSON')
                        ->rows(28)
                        ->readOnly()
                        ->columnSpanFull()
                        ->extraInputAttributes(['class' => 'font-mono text-xs']),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
            Action::make('sync_tripletex')
                ->label('Sync Tripletex')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->visible(fn (): bool => $this->canSyncToTripletex())
                ->action(function (): void {
                    Notification::make()
                        ->title('Syncing payout to Tripletex...')
                        ->body($this->record->stripe_payout_id)
                        ->info()
                        ->send();

                    try {
                        $sync = app(TripletexPayoutSync::class);
                        $ok = $sync->sync($this->record->id, true);
                        $run = TripletexSyncRun::query()
                            ->where('store_stripe_payout_id', $this->record->id)
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
        ];
    }

    protected function canSyncToTripletex(): bool
    {
        if ($this->record->status !== 'paid') {
            return false;
        }

        $tenant = Filament::getTenant();
        if (! $tenant || ! Addon::storeHasActiveAddon($tenant->getKey(), AddonType::Tripletex)) {
            return false;
        }

        if ((int) $this->record->store_id !== (int) $tenant->getKey()) {
            return false;
        }

        $this->record->loadMissing('store.tripletexIntegration');
        $integration = $this->record->store?->tripletexIntegration;

        return $integration?->isConnected() && $integration->sync_enabled;
    }

    /**
     * @return array<string, mixed>
     */
    protected function tripletexPayoutVoucherPreviewPayload(bool $resolveTripletexAccounts): array
    {
        $this->record->loadMissing('store.tripletexIntegration');
        $integration = $this->record->store?->tripletexIntegration;
        if (! $integration) {
            return ['ok' => false, 'error' => 'Tripletex integration is not configured for this store.'];
        }

        return app(TripletexSyncPreviewService::class)->previewPayout($this->record, $integration, $resolveTripletexAccounts);
    }
}
