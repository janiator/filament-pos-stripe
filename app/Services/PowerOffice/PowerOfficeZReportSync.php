<?php

namespace App\Services\PowerOffice;

use App\Enums\AddonType;
use App\Enums\PowerOfficeSyncRunStatus;
use App\Exceptions\PowerOffice\MissingPowerOfficeMappingException;
use App\Exceptions\PowerOffice\PowerOfficeUnresolvedGlAccountsException;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\PowerOfficeIntegration;
use App\Models\PowerOfficeSyncRun;
use App\Services\ZReport\ZReportPdfGenerator;
use Illuminate\Support\Facades\Log;

class PowerOfficeZReportSync
{
    public function __construct(
        protected PowerOfficeLedgerPayloadBuilder $ledgerPayloadBuilder,
        protected PowerOfficeApiClient $apiClient,
        protected PowerOfficeGeneralLedgerAccountResolver $generalLedgerAccountResolver,
        protected PowerOfficeManualVoucherPayloadFactory $manualVoucherPayloadFactory,
        protected ZReportPdfGenerator $zReportPdfGenerator,
    ) {}

    /**
     * Sync Z-report for a closed POS session to PowerOffice.
     *
     * @param  bool  $force  When true, sync even if auto_sync_on_z_report is disabled (manual / retry).
     */
    public function sync(int $posSessionId, bool $force = false): bool
    {
        $session = PosSession::query()->with('store')->find($posSessionId);
        if (! $session || $session->status !== 'closed') {
            return false;
        }

        $store = $session->store;
        if (! $store || ! Addon::storeHasActiveAddon($store->getKey(), AddonType::PowerOfficeGo)) {
            return false;
        }

        $integration = $store->powerOfficeIntegration;
        if (! $integration || ! $integration->isConnected()) {
            return false;
        }

        if (! $integration->sync_enabled) {
            return false;
        }

        if (! $integration->auto_sync_on_z_report && ! $force) {
            return false;
        }

        $zReport = $session->closing_data['z_report_data'] ?? null;
        if (! is_array($zReport)) {
            Log::warning('PowerOffice sync skipped: no Z-report snapshot on session', ['pos_session_id' => $session->id]);

            return false;
        }

        $idempotencyKey = $this->idempotencyKey($store->getKey(), $session->id);

        $existingSuccessRun = PowerOfficeSyncRun::query()
            ->where('idempotency_key', $idempotencyKey)
            ->where('status', PowerOfficeSyncRunStatus::Success)
            ->latest('id')
            ->first();

        if ($existingSuccessRun) {
            // Backfill final bilagsnr on old successful runs that only stored interim response data.
            if (! is_numeric($existingSuccessRun->journal_voucher_no) || (int) $existingSuccessRun->journal_voucher_no <= 0) {
                $existingVoucherId = data_get($existingSuccessRun->response_payload, 'Id')
                    ?? data_get($existingSuccessRun->response_payload, 'id');
                $existingVoucherId = is_string($existingVoucherId)
                    ? $existingVoucherId
                    : (is_numeric($existingVoucherId) ? (string) $existingVoucherId : null);

                $resolvedVoucherNo = $this->resolveFinalJournalVoucherNo($integration, $existingVoucherId, null);
                if ($resolvedVoucherNo !== null) {
                    $existingSuccessRun->journal_voucher_no = $resolvedVoucherNo;
                    $existingSuccessRun->save();
                }
            }

            return true;
        }

        $syncRun = PowerOfficeSyncRun::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'power_office_integration_id' => $integration->getKey(),
                'store_id' => $store->getKey(),
                'pos_session_id' => $session->id,
                'status' => PowerOfficeSyncRunStatus::Pending,
            ],
        );

        if ($syncRun->status === PowerOfficeSyncRunStatus::Success) {
            return true;
        }

        $syncRun->update([
            'status' => PowerOfficeSyncRunStatus::Processing,
            'attempts' => $syncRun->attempts + 1,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $payload = $this->ledgerPayloadBuilder->build($session, $integration, $zReport);
        } catch (MissingPowerOfficeMappingException $e) {
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
            $accountMap = $this->generalLedgerAccountResolver->resolveMapForAccountNos($integration, $accountCodes);
            $apiPayload = $this->manualVoucherPayloadFactory->build($payload, $accountMap, $idempotencyKey);
        } catch (PowerOfficeUnresolvedGlAccountsException $e) {
            $this->failRun($syncRun, $integration, 'PowerOffice GL accounts not found: '.implode(', ', $e->missingAccountNos));

            return false;
        } catch (\InvalidArgumentException $e) {
            $this->failRun($syncRun, $integration, 'PowerOffice voucher payload: '.$e->getMessage());

            return false;
        } catch (\Throwable $e) {
            $this->failRun($syncRun, $integration, $e->getMessage());

            return false;
        }

        try {
            $response = $this->apiClient->postLedgerEntry($integration, $apiPayload);
        } catch (\Throwable $e) {
            $this->failRun($syncRun, $integration, 'PowerOffice request failed: '.$e->getMessage());

            return false;
        }

        if (! $response->successful()) {
            $this->apiClient->logFailedResponse('ledger_post', $response);
            $message = 'PowerOffice HTTP '.$response->status().$this->apiClient->summarizeErrorBody($response);
            if ($response->status() === 403) {
                $message .= ' Go UI "Direktepostere manuelle bilag" needs POST …/Vouchers/ManualJournals; journal-entry workflow needs …/JournalEntryVouchers/ManualJournals (POWEROFFICE_LEDGER_POST_PATH). After changing privileges in Go, run: php artisan poweroffice:forget-token '.$store->slug.' then poweroffice:diagnose '.$store->slug.'. See docs/flutterflow/POWEROFFICE_GO_INTEGRATION.md.';
            }
            $this->failRun($syncRun, $integration, $message);

            return false;
        }

        $postedJson = $response->json();
        if (! is_array($postedJson)) {
            $postedJson = ['raw' => $response->body()];
        }

        $voucherId = $postedJson['Id'] ?? $postedJson['id'] ?? null;
        $voucherId = is_string($voucherId) ? $voucherId : (is_numeric($voucherId) ? (string) $voucherId : null);
        $journalVoucherNo = $postedJson['VoucherNo'] ?? $postedJson['voucherNo'] ?? null;
        $journalVoucherNo = is_numeric($journalVoucherNo) ? (int) $journalVoucherNo : null;
        $journalVoucherNo = $this->resolveFinalJournalVoucherNo($integration, $voucherId, $journalVoucherNo);

        $responsePayload = $postedJson;

        if ($voucherId !== null && $voucherId !== '') {
            try {
                $pdfBinary = $this->zReportPdfGenerator->render($session);
                $filename = $this->zReportPdfGenerator->suggestedFilename($session);
                $docResponse = $this->apiClient->putVoucherDocumentation($integration, $voucherId, $pdfBinary, $filename);
                if (! $docResponse->successful()) {
                    $this->apiClient->logFailedResponse('voucher_documentation_put', $docResponse);
                    $responsePayload['documentation_upload'] = [
                        'ok' => false,
                        'http_status' => $docResponse->status(),
                        'body' => $docResponse->body(),
                    ];
                    $syncRun->response_payload = $responsePayload;
                    $syncRun->journal_voucher_no = $journalVoucherNo;
                    $syncRun->save();
                    $this->failRun(
                        $syncRun,
                        $integration,
                        'PowerOffice voucher was posted but Z-report PDF upload failed (HTTP '.$docResponse->status().'). '.
                        'Voucher Id: '.$voucherId.'. Retry PDF only: php artisan poweroffice:attach-z-report-pdf '.$store->slug.' '.$session->id
                    );

                    return false;
                }
                $responsePayload['documentation_upload'] = [
                    'ok' => true,
                    'http_status' => $docResponse->status(),
                ];
            } catch (\Throwable $e) {
                Log::error('PowerOffice Z-report PDF upload failed', [
                    'pos_session_id' => $session->id,
                    'voucher_id' => $voucherId,
                    'exception' => $e->getMessage(),
                ]);
                $responsePayload['documentation_upload'] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
                $syncRun->response_payload = $responsePayload;
                $syncRun->journal_voucher_no = $journalVoucherNo;
                $syncRun->save();
                $this->failRun(
                    $syncRun,
                    $integration,
                    'PowerOffice voucher was posted but Z-report PDF failed: '.$e->getMessage().
                    ' Voucher Id: '.$voucherId.'. Retry: php artisan poweroffice:attach-z-report-pdf '.$store->slug.' '.$session->id
                );

                return false;
            }
        } else {
            Log::warning('PowerOffice manual journal response missing voucher Id; skipping PDF attachment', [
                'pos_session_id' => $session->id,
            ]);
            $responsePayload['documentation_upload'] = [
                'ok' => false,
                'skipped' => true,
                'reason' => 'missing_voucher_id_in_response',
            ];
        }

        $syncRun->status = PowerOfficeSyncRunStatus::Success;
        $syncRun->response_payload = $responsePayload;
        $syncRun->journal_voucher_no = $journalVoucherNo;
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
        return 'poweroffice_z_report_'.$storeId.'_'.$posSessionId;
    }

    protected function failRun(PowerOfficeSyncRun $syncRun, PowerOfficeIntegration $integration, string $message): void
    {
        $syncRun->status = PowerOfficeSyncRunStatus::Failed;
        $syncRun->finished_at = now();
        $syncRun->error_message = $message;
        $syncRun->save();

        $integration->last_error = $message;
        $integration->save();
    }

    protected function resolveFinalJournalVoucherNo(
        PowerOfficeIntegration $integration,
        ?string $voucherId,
        ?int $fallbackVoucherNo,
    ): ?int {
        if ($voucherId === null || $voucherId === '') {
            return (is_numeric($fallbackVoucherNo) && $fallbackVoucherNo > 0) ? (int) $fallbackVoucherNo : null;
        }

        $postPath = trim((string) config('poweroffice.ledger.post_path'));
        if ($postPath === '') {
            return (is_numeric($fallbackVoucherNo) && $fallbackVoucherNo > 0) ? (int) $fallbackVoucherNo : null;
        }

        $detailPath = '/'.trim($postPath, '/').'/'.rawurlencode($voucherId);

        try {
            $detailResponse = $this->apiClient->get($integration, $detailPath);
            if (! $detailResponse->successful()) {
                $this->apiClient->logFailedResponse('manual_journal_get_detail', $detailResponse);

                return (is_numeric($fallbackVoucherNo) && $fallbackVoucherNo > 0) ? (int) $fallbackVoucherNo : null;
            }

            $detailJson = $detailResponse->json();
            if (! is_array($detailJson)) {
                return (is_numeric($fallbackVoucherNo) && $fallbackVoucherNo > 0) ? (int) $fallbackVoucherNo : null;
            }

            $resolvedVoucherNo = $detailJson['VoucherNo'] ?? $detailJson['voucherNo'] ?? null;
            if (is_numeric($resolvedVoucherNo) && (int) $resolvedVoucherNo > 0) {
                return (int) $resolvedVoucherNo;
            }
        } catch (\Throwable $e) {
            Log::warning('PowerOffice manual journal detail lookup failed', [
                'voucher_id' => $voucherId,
                'error' => $e->getMessage(),
            ]);
        }

        return (is_numeric($fallbackVoucherNo) && $fallbackVoucherNo > 0) ? (int) $fallbackVoucherNo : null;
    }
}
