<?php

namespace App\Services\Tripletex;

use App\Enums\AddonType;
use App\Enums\TripletexSyncRunStatus;
use App\Enums\TripletexSyncType;
use App\Exceptions\Tripletex\MissingTripletexMappingException;
use App\Exceptions\Tripletex\TripletexUnresolvedLedgerAccountsException;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\TripletexIntegration;
use App\Models\TripletexSyncRun;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TripletexZReportSync
{
    public function __construct(
        protected TripletexZReportLedgerPayloadBuilder $ledgerPayloadBuilder,
        protected TripletexApiClient $apiClient,
        protected TripletexAccountResolver $accountResolver,
        protected TripletexManualVoucherPayloadFactory $manualVoucherPayloadFactory,
        protected TripletexVoucherSyncSkipRecorder $skipRecorder,
    ) {}

    /**
     * Sync Z-report for a closed POS session to Tripletex.
     *
     * @param  bool  $force  When true, sync even if auto_sync_on_z_report is disabled (manual / retry).
     */
    public function sync(int $posSessionId, bool $force = false): bool
    {
        $session = PosSession::query()->with('store')->find($posSessionId);
        if (! $session || $session->status !== 'closed') {
            Log::info('Tripletex voucher sync skipped', [
                'context' => 'z_report',
                'reason' => 'session_not_found_or_not_closed',
                'pos_session_id' => $posSessionId,
            ]);

            return false;
        }

        $store = $session->store;
        if (! $store || ! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            Log::info('Tripletex voucher sync skipped', [
                'context' => 'z_report',
                'reason' => 'tripletex_addon_inactive_or_missing_store',
                'pos_session_id' => $session->id,
                'store_id' => $store?->getKey(),
            ]);

            return false;
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            Log::info('Tripletex voucher sync skipped', [
                'context' => 'z_report',
                'reason' => 'tripletex_integration_missing_or_disconnected',
                'pos_session_id' => $session->id,
                'store_id' => $store->getKey(),
            ]);

            return false;
        }

        $idempotencyKey = $this->idempotencyKey($store->getKey(), $session->id);

        if ($this->skipRecorder->hasSuccessfulRun($idempotencyKey)) {
            return true;
        }

        if (! $integration->sync_enabled) {
            $this->skipRecorder->record(
                $integration,
                $store->getKey(),
                $idempotencyKey,
                TripletexSyncType::ZReport,
                $session->id,
                null,
                'Tripletex sync is turned off for this store (sync disabled).',
            );

            return false;
        }

        if (! $integration->auto_sync_on_z_report && ! $force) {
            $this->skipRecorder->record(
                $integration,
                $store->getKey(),
                $idempotencyKey,
                TripletexSyncType::ZReport,
                $session->id,
                null,
                'Automatic Z-report posting to Tripletex is disabled. Enable it in the integration settings or run a manual sync.',
            );

            return false;
        }

        $zReport = $this->materializeZReportData($session);
        if ($zReport === null) {
            Log::warning('Tripletex sync skipped: no Z-report snapshot on session', ['pos_session_id' => $session->id]);
            $syncRun = $this->firstOrCreatePendingRun($integration, $store->getKey(), $session->id);
            $this->failRun($syncRun, $integration, 'No Z-report snapshot found for session.');

            return false;
        }

        if (! $this->isZReportEligibleForSync($zReport)) {
            $this->skipRecorder->record(
                $integration,
                $store->getKey(),
                $idempotencyKey,
                TripletexSyncType::ZReport,
                $session->id,
                null,
                $this->describeZReportIneligibility($zReport),
            );

            return false;
        }

        $syncRun = TripletexSyncRun::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'tripletex_integration_id' => $integration->getKey(),
                'store_id' => $store->getKey(),
                'sync_type' => TripletexSyncType::ZReport,
                'pos_session_id' => $session->id,
                'store_stripe_payout_id' => null,
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
            $payload = $this->ledgerPayloadBuilder->build($session, $integration, $zReport);
        } catch (MissingTripletexMappingException $e) {
            $this->failRun($syncRun, $integration, 'Missing mapping: '.implode(', ', $e->missingBasisKeys));

            return false;
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
            $this->failRun($syncRun, $integration, 'Tripletex voucher payload: '.$e->getMessage());

            return false;
        } catch (\Throwable $e) {
            $this->failRun($syncRun, $integration, $e->getMessage());

            return false;
        }

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('tripletex_voucher_post', $response);
            $message = $this->apiClient->describeFailedVoucherResponse($response);
            $this->failRun($syncRun, $integration, $message);

            return false;
        }

        $postedJson = $response->json();
        if (! is_array($postedJson)) {
            $postedJson = ['raw' => $response->body()];
        }

        $voucherId = data_get($postedJson, 'value.id')
            ?? data_get($postedJson, 'id');
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

    public function idempotencyKey(int $storeId, int $posSessionId): string
    {
        return 'tripletex:z-report:'.$storeId.':'.$posSessionId;
    }

    protected function firstOrCreatePendingRun(TripletexIntegration $integration, int $storeId, int $posSessionId): TripletexSyncRun
    {
        return TripletexSyncRun::query()->firstOrCreate(
            ['idempotency_key' => $this->idempotencyKey($storeId, $posSessionId)],
            [
                'tripletex_integration_id' => $integration->getKey(),
                'store_id' => $storeId,
                'sync_type' => TripletexSyncType::ZReport,
                'pos_session_id' => $posSessionId,
                'store_stripe_payout_id' => null,
                'status' => TripletexSyncRunStatus::Pending,
            ],
        );
    }

    protected function failRun(TripletexSyncRun $syncRun, TripletexIntegration $integration, string $message): void
    {
        $syncRun->status = TripletexSyncRunStatus::Failed;
        $syncRun->finished_at = now();
        $syncRun->error_message = $message;
        $syncRun->save();

        $integration->last_error = $message;
        $integration->save();

        $this->sendFailureNotificationToStoreUsers($syncRun, $message);
    }

    protected function sendFailureNotificationToStoreUsers(TripletexSyncRun $syncRun, string $message): void
    {
        $store = $syncRun->store()->with('users')->first();
        if (! $store) {
            return;
        }

        $sessionNumber = null;
        $session = PosSession::query()->find($syncRun->pos_session_id);
        if ($session) {
            $sessionNumber = $session->session_number;
        }

        foreach ($store->users as $user) {
            Notification::make()
                ->title('Tripletex sync failed')
                ->body($sessionNumber
                    ? "Session {$sessionNumber}: {$message}"
                    : $message)
                ->danger()
                ->sendToDatabase($user);
        }
    }

    public function isSessionEligibleForSync(PosSession $session): bool
    {
        if ($session->status !== 'closed') {
            return false;
        }

        $zReport = $session->closing_data['z_report_data'] ?? null;
        if (! is_array($zReport)) {
            return false;
        }

        return $this->isZReportEligibleForSync($zReport);
    }

    /**
     * Load or generate `closing_data.z_report_data` for a closed session (does not check eligibility).
     *
     * @return array<string, mixed>|null
     */
    public function materializeZReportData(PosSession $session): ?array
    {
        if ($session->status !== 'closed') {
            return null;
        }

        $zReport = $session->closing_data['z_report_data'] ?? null;
        if (! is_array($zReport)) {
            try {
                PosSessionsTable::generateZReport($session);
                $session->refresh();
                $zReport = $session->closing_data['z_report_data'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Tripletex failed to build Z-report snapshot', [
                    'pos_session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return is_array($zReport) ? $zReport : null;
    }

    /**
     * Human-readable explanation when {@see isZReportEligibleForSync} is false.
     *
     * @param  array<string, mixed>  $zReport
     */
    public function describeZReportIneligibility(array $zReport): string
    {
        $hasTransactionCount = array_key_exists('transactions_count', $zReport)
            || array_key_exists('transaction_count', $zReport);
        $transactionCount = (int) ($zReport['transactions_count'] ?? $zReport['transaction_count'] ?? 0);
        if ($hasTransactionCount && $transactionCount <= 0) {
            return 'Z-report shows zero transactions, so no Tripletex voucher is posted.';
        }

        return 'Z-report has no non-zero amounts (net, total, cash, card, mobile, or other), so no Tripletex voucher is posted.';
    }

    /**
     * @param  array<string, mixed>  $zReport
     */
    public function isZReportEligibleForSync(array $zReport): bool
    {
        $hasTransactionCount = array_key_exists('transactions_count', $zReport)
            || array_key_exists('transaction_count', $zReport);
        $transactionCount = (int) ($zReport['transactions_count'] ?? $zReport['transaction_count'] ?? 0);
        if ($hasTransactionCount && $transactionCount <= 0) {
            return false;
        }

        $valueFields = [
            'net_amount',
            'total_amount',
            'net_cash_amount',
            'net_card_amount',
            'net_mobile_amount',
            'net_other_amount',
        ];

        foreach ($valueFields as $field) {
            $value = $zReport[$field] ?? null;
            if (is_numeric($value) && (int) $value !== 0) {
                return true;
            }
        }

        return false;
    }
}
