<?php

namespace App\Observers;

use App\Enums\AddonType;
use App\Jobs\SyncPowerOfficeZReportJob;
use App\Models\Addon;
use App\Models\PosEvent;
use App\Models\PosSession;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeZReportSync;

class PosEventObserver
{
    /**
     * After a Z-report is logged, queue PowerOffice sync when the add-on is enabled.
     */
    public function created(PosEvent $event): void
    {
        if ($event->event_code !== PosEvent::EVENT_Z_REPORT) {
            return;
        }

        if ($event->pos_session_id === null || $event->store_id === null) {
            return;
        }

        $store = Store::query()->find($event->store_id);
        if (! $store) {
            return;
        }

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::PowerOfficeGo)) {
            return;
        }

        $integration = $store->powerOfficeIntegration;
        if (! $integration || ! $integration->isConnected() || ! $integration->sync_enabled || ! $integration->auto_sync_on_z_report) {
            return;
        }

        $session = PosSession::query()->find($event->pos_session_id);
        if (! $session) {
            return;
        }

        if (! app(PowerOfficeZReportSync::class)->isSessionEligibleForSync($session)) {
            return;
        }

        SyncPowerOfficeZReportJob::dispatch($event->pos_session_id);
    }
}
