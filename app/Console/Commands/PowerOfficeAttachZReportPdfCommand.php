<?php

namespace App\Console\Commands;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\PowerOfficeSyncRun;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeApiClient;
use App\Services\ZReport\ZReportPdfGenerator;
use Illuminate\Console\Command;

class PowerOfficeAttachZReportPdfCommand extends Command
{
    protected $signature = 'poweroffice:attach-z-report-pdf
                            {store_slug : Store slug}
                            {pos_session_id : POS session id}
                            {--voucher-id= : PowerOffice voucher Guid (if not read from last sync run response)}';

    protected $description = 'Upload Z-report PDF to an already-posted PowerOffice voucher (recovery)';

    public function handle(PowerOfficeApiClient $apiClient, ZReportPdfGenerator $pdfGenerator): int
    {
        $slug = (string) $this->argument('store_slug');
        $sessionId = (int) $this->argument('pos_session_id');

        $store = Store::query()->where('slug', $slug)->first();
        if (! $store) {
            $this->error("Store not found: {$slug}");

            return self::FAILURE;
        }

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::PowerOfficeGo)) {
            $this->error('PowerOffice Go add-on is not active for this store.');

            return self::FAILURE;
        }

        $integration = $store->powerOfficeIntegration;
        if (! $integration || ! $integration->isConnected()) {
            $this->error('PowerOffice integration is missing or not connected.');

            return self::FAILURE;
        }

        $session = PosSession::query()
            ->where('store_id', $store->getKey())
            ->whereKey($sessionId)
            ->first();

        if (! $session) {
            $this->error("POS session {$sessionId} not found for this store.");

            return self::FAILURE;
        }

        $voucherId = $this->option('voucher-id');
        if (! is_string($voucherId) || $voucherId === '') {
            $run = PowerOfficeSyncRun::query()
                ->where('pos_session_id', $session->id)
                ->orderByDesc('id')
                ->first();
            $payload = $run?->response_payload;
            if (is_array($payload)) {
                $voucherId = $payload['Id'] ?? $payload['id'] ?? null;
            }
        }

        if (! is_string($voucherId) || $voucherId === '') {
            $this->error('Could not resolve voucher Guid. Pass --voucher-id=… from PowerOffice or re-run a failed sync that stored the Id in response_payload.');

            return self::FAILURE;
        }

        $this->info("Uploading Z-report PDF for voucher {$voucherId}…");

        try {
            $pdf = $pdfGenerator->render($session);
            $filename = $pdfGenerator->suggestedFilename($session);
            $response = $apiClient->putVoucherDocumentation($integration, $voucherId, $pdf, $filename);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('HTTP '.$response->status().': '.$response->body());
            $apiClient->logFailedResponse('voucher_documentation_put_cli', $response);

            return self::FAILURE;
        }

        $run = PowerOfficeSyncRun::query()
            ->where('pos_session_id', $session->id)
            ->orderByDesc('id')
            ->first();

        if ($run) {
            $merged = is_array($run->response_payload) ? $run->response_payload : [];
            $merged['documentation_upload'] = [
                'ok' => true,
                'http_status' => $response->status(),
                'attached_via_cli_at' => now()->toIso8601String(),
            ];
            $run->response_payload = $merged;
            if ($run->error_message && str_contains((string) $run->error_message, 'PDF')) {
                $run->error_message = null;
                $run->status = \App\Enums\PowerOfficeSyncRunStatus::Success;
                $run->finished_at = now();
            }
            $run->save();
        }

        $this->info('PDF uploaded successfully.');

        return self::SUCCESS;
    }
}
