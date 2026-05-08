<?php

namespace App\Filament\Actions;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\StoreStripePayout;
use App\Services\Tripletex\TripletexSyncPreviewService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Icons\Heroicon;

/**
 * Slide-over preview of the Tripletex voucher JSON (ledger lines; optional Tripletex account resolution).
 */
final class TripletexVoucherPreviewAction
{
    public static function makeTableActionForZReport(): Action
    {
        return Action::make('preview_tripletex_voucher')
            ->label(__('Preview Tripletex voucher'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->slideOver()
            ->modalHeading(__('Tripletex voucher preview'))
            ->modalDescription(__('Ledger lines for this session’s Z-report. Turn on account resolution to call Tripletex and include the exact JSON for POST /ledger/voucher.'))
            ->modalWidth('4xl')
            ->visible(fn (PosSession $record): bool => self::canPreviewZReport($record))
            ->fillForm(fn (PosSession $record): array => [
                '_record_id' => $record->getKey(),
                'resolve_tripletex_accounts' => false,
                'preview_json' => self::encodeZReportPreview($record, false),
            ])
            ->form(self::previewFormSchema(
                resolveUsing: function (bool $resolve, Get $get): string {
                    $id = (int) $get('_record_id');
                    $session = PosSession::query()->find($id);
                    if (! $session instanceof PosSession) {
                        return json_encode(['ok' => false, 'error' => 'Session not found.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    return self::encodeZReportPreview($session, $resolve);
                },
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    public static function makeTableActionForPayout(): Action
    {
        return Action::make('preview_tripletex_voucher')
            ->label(__('Preview Tripletex voucher'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->slideOver()
            ->modalHeading(__('Tripletex voucher preview'))
            ->modalDescription(__('Ledger lines for this Stripe payout. Turn on account resolution to call Tripletex and include the exact JSON for POST /ledger/voucher.'))
            ->modalWidth('4xl')
            ->visible(fn (StoreStripePayout $record): bool => self::canPreviewPayout($record))
            ->fillForm(fn (StoreStripePayout $record): array => [
                '_record_id' => $record->getKey(),
                'resolve_tripletex_accounts' => false,
                'preview_json' => self::encodePayoutPreview($record, false),
            ])
            ->form(self::previewFormSchema(
                resolveUsing: function (bool $resolve, Get $get): string {
                    $id = (int) $get('_record_id');
                    $payout = StoreStripePayout::query()->find($id);
                    if (! $payout instanceof StoreStripePayout) {
                        return json_encode(['ok' => false, 'error' => 'Payout not found.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    return self::encodePayoutPreview($payout, $resolve);
                },
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /**
     * @param  callable(bool $resolve, Get $get): string  $resolveUsing
     * @return list<\Filament\Forms\Components\Component>
     */
    protected static function previewFormSchema(callable $resolveUsing): array
    {
        return [
            Hidden::make('_record_id'),
            Toggle::make('resolve_tripletex_accounts')
                ->label(__('Resolve Tripletex account IDs (calls Tripletex API)'))
                ->helperText(__('Creates a short-lived session token and resolves each ledger account number used in the voucher.'))
                ->default(false)
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get) use ($resolveUsing): void {
                    $json = $resolveUsing((bool) $state, $get);
                    $set('preview_json', $json);
                }),
            Textarea::make('preview_json')
                ->label(__('Preview JSON'))
                ->rows(28)
                ->readOnly()
                ->columnSpanFull()
                ->extraInputAttributes(['class' => 'font-mono text-xs']),
        ];
    }

    public static function canPreviewZReport(PosSession $record): bool
    {
        if ($record->status !== 'closed') {
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

        return (bool) $record->store?->tripletexIntegration?->isConnected();
    }

    public static function canPreviewPayout(StoreStripePayout $record): bool
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

        return (bool) $record->store?->tripletexIntegration?->isConnected();
    }

    protected static function encodeZReportPreview(PosSession $session, bool $resolve): string
    {
        $session->loadMissing('store.tripletexIntegration');
        $integration = $session->store?->tripletexIntegration;
        if (! $integration) {
            return json_encode(['ok' => false, 'error' => 'Tripletex integration is not configured for this store.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $payload = app(TripletexSyncPreviewService::class)->previewZReport($session, $integration, $resolve);

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function encodePayoutPreview(StoreStripePayout $payout, bool $resolve): string
    {
        $payout->loadMissing('store.tripletexIntegration');
        $integration = $payout->store?->tripletexIntegration;
        if (! $integration) {
            return json_encode(['ok' => false, 'error' => 'Tripletex integration is not configured for this store.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $payload = app(TripletexSyncPreviewService::class)->previewPayout($payout, $integration, $resolve);

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
