<?php

namespace App\Http\Controllers\Api\Tripletex;

use App\Enums\AddonType;
use App\Enums\TripletexSyncType;
use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\SyncTripletexPayoutJob;
use App\Jobs\SyncTripletexZReportJob;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreStripePayout;
use App\Models\TripletexSyncRun;
use App\Services\Tripletex\TripletexHistoricalSyncService;
use App\Services\Tripletex\TripletexSyncPreviewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripletexSyncController extends BaseApiController
{
    public function syncZReport(Request $request, string $posSession): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            return response()->json(['message' => 'Tripletex add-on is not enabled for this store.'], 403);
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            return response()->json(['message' => 'Tripletex is not connected for this store.'], 403);
        }

        if (! $integration->sync_enabled) {
            return response()->json(['message' => 'Tripletex sync is turned off for this store.'], 403);
        }

        $session = PosSession::query()
            ->forStore((int) $store->getKey())
            ->whereKey($posSession)
            ->firstOrFail();

        if ($session->status !== 'closed') {
            return response()->json(['message' => 'Session must be closed to sync Z-report.'], 422);
        }

        SyncTripletexZReportJob::dispatch($session->id, true);

        return response()->json([
            'message' => 'Tripletex Z-report sync queued.',
            'pos_session_id' => $session->id,
        ]);
    }

    public function syncPayout(Request $request, string $payout): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            return response()->json(['message' => 'Tripletex add-on is not enabled for this store.'], 403);
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            return response()->json(['message' => 'Tripletex is not connected for this store.'], 403);
        }

        if (! $integration->sync_enabled) {
            return response()->json(['message' => 'Tripletex sync is turned off for this store.'], 403);
        }

        $payoutRow = StoreStripePayout::query()
            ->where('store_id', $store->getKey())
            ->whereKey($payout)
            ->firstOrFail();

        if ($payoutRow->status !== 'paid') {
            return response()->json(['message' => 'Payout must be in paid status to sync.'], 422);
        }

        SyncTripletexPayoutJob::dispatch($payoutRow->id, true);

        return response()->json([
            'message' => 'Tripletex payout sync queued.',
            'store_stripe_payout_id' => $payoutRow->id,
        ]);
    }

    public function retry(Request $request, string $syncRun): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            return response()->json(['message' => 'Tripletex add-on is not enabled for this store.'], 403);
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            return response()->json(['message' => 'Tripletex is not connected for this store.'], 403);
        }

        if (! $integration->sync_enabled) {
            return response()->json(['message' => 'Tripletex sync is turned off for this store.'], 403);
        }

        $run = TripletexSyncRun::query()
            ->where('store_id', $store->getKey())
            ->whereKey($syncRun)
            ->firstOrFail();

        if ($run->sync_type === TripletexSyncType::ZReport && $run->pos_session_id) {
            SyncTripletexZReportJob::dispatch($run->pos_session_id, true);

            return response()->json([
                'message' => 'Tripletex Z-report sync retry queued.',
                'pos_session_id' => $run->pos_session_id,
            ]);
        }

        if ($run->sync_type === TripletexSyncType::Payout && $run->store_stripe_payout_id) {
            SyncTripletexPayoutJob::dispatch($run->store_stripe_payout_id, true);

            return response()->json([
                'message' => 'Tripletex payout sync retry queued.',
                'store_stripe_payout_id' => $run->store_stripe_payout_id,
            ]);
        }

        return response()->json(['message' => 'This sync run cannot be retried (missing references).'], 422);
    }

    public function previewZReport(Request $request, string $posSession): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            return response()->json(['message' => 'Tripletex add-on is not enabled for this store.'], 403);
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            return response()->json(['message' => 'Tripletex is not connected for this store.'], 403);
        }

        $session = PosSession::query()
            ->forStore((int) $store->getKey())
            ->whereKey($posSession)
            ->firstOrFail();

        $resolve = filter_var($request->query('resolve_accounts', false), FILTER_VALIDATE_BOOL);

        $preview = app(TripletexSyncPreviewService::class)->previewZReport($session, $integration, $resolve);

        return response()->json($preview);
    }

    public function previewPayout(Request $request, string $payout): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            return response()->json(['message' => 'Tripletex add-on is not enabled for this store.'], 403);
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            return response()->json(['message' => 'Tripletex is not connected for this store.'], 403);
        }

        $payoutRow = StoreStripePayout::query()
            ->where('store_id', $store->getKey())
            ->whereKey($payout)
            ->firstOrFail();

        $resolve = filter_var($request->query('resolve_accounts', false), FILTER_VALIDATE_BOOL);

        $preview = app(TripletexSyncPreviewService::class)->previewPayout($payoutRow, $integration, $resolve);

        return response()->json($preview);
    }

    public function historical(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            return response()->json(['message' => 'Tripletex add-on is not enabled for this store.'], 403);
        }

        $integration = $store->tripletexIntegration;
        if (! $integration || ! $integration->isConnected()) {
            return response()->json(['message' => 'Tripletex is not connected for this store.'], 403);
        }

        if (! $integration->sync_enabled) {
            return response()->json(['message' => 'Tripletex sync is turned off for this store.'], 403);
        }

        $data = $request->validate([
            'type' => 'required|string|in:z_report,payout',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:500',
            'only_missing' => 'nullable|boolean',
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null;
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null;
        if ($from && $to && $from->greaterThan($to)) {
            return response()->json(['message' => '`from` must be on or before `to`.'], 422);
        }
        $limit = (int) ($data['limit'] ?? 50);
        $onlyMissing = (bool) ($data['only_missing'] ?? true);

        $service = app(TripletexHistoricalSyncService::class);

        if ($data['type'] === 'payout') {
            $result = $service->queuePayouts($store, $from, $to, $limit, $onlyMissing);

            return response()->json([
                'message' => 'Tripletex payout historical sync jobs queued.',
                'queued' => $result['queued'],
                'skipped' => $result['skipped'],
            ]);
        }

        $result = $service->queueZReports($store, $from, $to, $limit, $onlyMissing);

        return response()->json([
            'message' => 'Tripletex Z-report historical sync jobs queued.',
            'queued' => $result['queued'],
            'skipped' => $result['skipped'],
        ]);
    }
}
