<?php

namespace App\Http\Controllers\Api\PowerOffice;

use App\Enums\AddonType;
use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\SyncPowerOfficeZReportJob;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\PowerOfficeSyncRun;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PowerOfficeSyncController extends BaseApiController
{
    public function syncZReport(Request $request, string $posSession): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::PowerOfficeGo)) {
            return response()->json(['message' => 'PowerOffice add-on is not enabled for this store.'], 403);
        }

        $integration = $store->powerOfficeIntegration;
        if (! $integration || ! $integration->sync_enabled) {
            return response()->json(['message' => 'PowerOffice sync is turned off for this store.'], 403);
        }

        $session = PosSession::query()
            ->where('store_id', $store->getKey())
            ->whereKey($posSession)
            ->firstOrFail();

        if ($session->status !== 'closed') {
            return response()->json(['message' => 'Session must be closed to sync Z-report.'], 422);
        }

        SyncPowerOfficeZReportJob::dispatch($session->id, true);

        return response()->json([
            'message' => 'PowerOffice sync queued.',
            'pos_session_id' => $session->id,
        ]);
    }

    public function retry(Request $request, string $syncRun): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        $integration = $store->powerOfficeIntegration;
        if (! $integration || ! $integration->sync_enabled) {
            return response()->json(['message' => 'PowerOffice sync is turned off for this store.'], 403);
        }

        $run = PowerOfficeSyncRun::query()
            ->where('store_id', $store->getKey())
            ->whereKey($syncRun)
            ->firstOrFail();

        SyncPowerOfficeZReportJob::dispatch($run->pos_session_id, true);

        return response()->json([
            'message' => 'PowerOffice sync retry queued.',
            'pos_session_id' => $run->pos_session_id,
        ]);
    }
}
