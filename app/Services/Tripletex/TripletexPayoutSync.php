<?php

namespace App\Services\Tripletex;

use App\Enums\AddonType;
use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Exceptions\Tripletex\TripletexUnresolvedLedgerAccountsException;
use App\Models\Addon;
use App\Models\StoreStripePayout;
use App\Models\TripletexIntegration;
use App\Models\TripletexSyncRun;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TripletexPayoutSync
{
    public function __construct(
        protected TripletexPayoutLedgerPayloadBuilder $ledgerPayloadBuilder,
        protected TripletexPayoutBalanceTransactionHydrator $payoutBalanceTransactionHydrator,
        protected TripletexApiClient $apiClient,
        protected TripletexAccountResolver $accountResolver,
        protected TripletexManualVoucherPayloadFactory $manualVoucherPayloadFactory,
        protected TripletexVoucherSyncSkipRecorder $skipRecorder,
    ) {}

    /**
     * Post a Tripletex voucher for a paid Stripe payout mirror row.
     *
     * @param  bool  $force  When true, sync even if auto_sync_payouts is disabled.
     */
    public function sync(int $storeStripePayoutId, bool $force = false): bool
    {
        $payout = StoreStripePayout::query()->with('store')->find($storeStripePayoutId);
        if (! $payout) {
            Log::info('Tripletex voucher sync skipped', [
                'context' => 'payout',
                'reason' => 'payout_row_not_found',
                'store_stripe_payout_id' => $storeStripePayoutId,
            ]);

            return false;
        }

        $store = $payout->store;
        if (! $store || ! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            Log::info('Tripletex voucher sync skipped', [
                'context' => 'payout',
                'reason' => 'tripletex_addon_inactive_or_missing_store',
                'store_stripe_payout_id' => $payout->id,
                'store_id' => $store?->getKey(),
            ]);

            return false;
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            Log::info('Tripletex voucher sync skipped', [
                'context' => 'payout',
                'reason' => 'tripletex_integration_missing_or_disconnected',
                'store_stripe_payout_id' => $payout->id,
                'store_id' => $store->getKey(),
            ]);

            return false;
        }

        $idempotencyKey = $this->idempotencyKey($store->getKey(), (string) $payout->stripe_payout_id);

        if ($this->skipRecorder->hasSuccessfulRun($idempotencyKey)) {
            return true;
        }

        if (! $integration->sync_enabled) {
            $this->skipRecorder->record(
                $integration,
                $store->getKey(),
                $idempotencyKey,
                TripletexSyncType::Payout,
                null,
                $payout->id,
                'Tripletex sync is turned off for this store (sync disabled).',
            );

            return false;
        }

        if (! $integration->auto_sync_payouts && ! $force) {
            $this->skipRecorder->record(
                $integration,
                $store->getKey(),
                $idempotencyKey,
                TripletexSyncType::Payout,
                null,
                $payout->id,
                'Automatic payout posting to Tripletex is disabled. Enable it in the integration settings or run a manual sync.',
            );

            return false;
        }

        if ($payout->status !== 'paid') {
            $this->skipRecorder->record(
                $integration,
                $store->getKey(),
                $idempotencyKey,
                TripletexSyncType::Payout,
                null,
                $payout->id,
                'Stripe payout is not in paid status yet, so no Tripletex voucher is posted.',
            );

            return false;
        }

        $this->payoutBalanceTransactionHydrator->hydrateIfMissing($store, $payout);

        $syncRun = TripletexSyncRun::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'tripletex_integration_id' => $integration->getKey(),
                'store_id' => $store->getKey(),
                'sync_type' => TripletexSyncType::Payout,
                'pos_session_id' => null,
                'store_stripe_payout_id' => $payout->id,
                'status' => TripletexSyncRunStatus::Pending,
            ],
        );

        if ($syncRun->status === TripletexSyncRunStatus::Success) {
            return true;
        }

        $syncRun->update([
            'status' => TripletexSyncRunStatus::Processing,
            'attempts' => $syncRun->attempts + 1,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $payload = $this->ledgerPayloadBuilder->build($store, $integration, $payout);
        } catch (\Throwable $e) {
            $this->failRun($syncRun, $integration, $e->getMessage());

            return false;
        }

        $syncRun->request_payload = $payload;
        $syncRun->save();

        $accountCodes = [];
        foreach ($payload['lines'] ?? [] as $line) {
            if (is_array($line) && filled($line['account'] ?? null)) {
                $accountCodes[] = trim((string) $line['account']);
            }
        }
        $accountCodes = array_values(array_unique($accountCodes));

        try {
            $sessionToken = $this->apiClient->createSessionToken($integration);
            $accountMap = $this->accountResolver->resolveMapForAccountNos($integration, $sessionToken, $accountCodes);
            $apiPayload = $this->manualVoucherPayloadFactory->build($payload, $accountMap);
            $response = $this->apiClient->postVoucher($sessionToken, $integration->environment, $apiPayload);
        } catch (TripletexUnresolvedLedgerAccountsException $e) {
            $this->failRun($syncRun, $integration, 'Tripletex ledger accounts not found: '.implode(', ', $e->missingAccountNos));

            return false;
        } catch (\InvalidArgumentException $e) {
            $this->failRun($syncRun, $integration, 'Tripletex payout voucher: '.$e->getMessage());

            return false;
        } catch (\Throwable $e) {
            $this->failRun($syncRun, $integration, $e->getMessage());

            return false;
        }

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('tripletex_payout_voucher_post', $response);
            $message = 'Tripletex HTTP '.$response->status().$this->apiClient->summarizeErrorBody($response);
            $this->failRun($syncRun, $integration, $message);

            return false;
        }

        $postedJson = $response->json();
        if (! is_array($postedJson)) {
            $postedJson = ['raw' => $response->body()];
        }

        $voucherId = data_get($postedJson, 'value.id') ?? data_get($postedJson, 'id');
        $voucherId = is_string($voucherId) ? $voucherId : (is_numeric($voucherId) ? (string) $voucherId : null);

        $syncRun->status = TripletexSyncRunStatus::Success;
        $syncRun->response_payload = $postedJson;
        $syncRun->tripletex_voucher_id = $voucherId;
        $syncRun->finished_at = now();
        $syncRun->error_message = null;
        $syncRun->save();

        $integration->last_synced_at = now();
        $integration->last_error = null;
        $integration->save();

        return true;
    }

    public function idempotencyKey(int $storeId, string $stripePayoutId): string
    {
        return 'tripletex:payout:'.$storeId.':'.$stripePayoutId;
    }

    protected function failRun(TripletexSyncRun $syncRun, TripletexIntegration $integration, string $message): void
    {
        $syncRun->status = TripletexSyncRunStatus::Failed;
        $syncRun->finished_at = now();
        $syncRun->error_message = $message;
        $syncRun->save();

        $integration->last_error = $message;
        $integration->save();

        $store = $syncRun->store()->with('users')->first();
        if (! $store) {
            Log::warning('Tripletex payout sync failed (no store for notification)', [
                'sync_run_id' => $syncRun->id,
                'message' => $message,
            ]);

            return;
        }

        foreach ($store->users as $user) {
            Notification::make()
                ->title('Tripletex payout sync failed')
                ->body($message)
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
