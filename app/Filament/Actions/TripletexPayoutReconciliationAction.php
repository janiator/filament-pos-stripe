<?php

namespace App\Filament\Actions;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\StoreStripePayout;
use App\Services\Tripletex\TripletexPayoutReconciliationService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;

final class TripletexPayoutReconciliationAction
{
    public static function makeTableAction(): Action
    {
        return Action::make('tripletex_payout_reconciliation')
            ->label(__('Reconcile'))
            ->icon('heroicon-o-scale')
            ->color('gray')
            ->slideOver()
            ->modalHeading(__('Tripletex payout reconciliation'))
            ->modalDescription(__('Compares Stripe mirror totals to the last successful Tripletex payout voucher stored on the sync run (read-only).'))
            ->modalWidth('3xl')
            ->visible(fn (StoreStripePayout $record): bool => self::visibleForRecord($record))
            ->fillForm(fn (StoreStripePayout $record): array => [
                'report_json' => self::encodeReport($record),
            ])
            ->form([
                Textarea::make('report_json')
                    ->label(__('Reconciliation report'))
                    ->rows(26)
                    ->readOnly()
                    ->columnSpanFull()
                    ->extraInputAttributes(['class' => 'font-mono text-xs']),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    protected static function visibleForRecord(StoreStripePayout $record): bool
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

    protected static function encodeReport(StoreStripePayout $payout): string
    {
        $payout->loadMissing('store.tripletexIntegration');
        $report = app(TripletexPayoutReconciliationService::class)->reconcile(
            $payout,
            $payout->store?->tripletexIntegration,
        );

        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
