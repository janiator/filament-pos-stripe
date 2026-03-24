<?php

namespace App\Console\Commands;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\PosSession;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeZReportSync;
use Illuminate\Console\Command;

class PowerOfficeSyncZReportCommand extends Command
{
    protected $signature = 'poweroffice:sync-z-report
                            {store_slug : Store slug (e.g. jobberiet-as)}
                            {pos_session_id? : POS session id; default: latest closed session with Z-report data}';

    protected $description = 'Run PowerOffice Z-report sync (manual journal + PDF) for a store session';

    public function handle(PowerOfficeZReportSync $sync): int
    {
        $slug = (string) $this->argument('store_slug');
        $store = Store::query()->where('slug', $slug)->first();
        if (! $store) {
            $this->error("Store not found for slug: {$slug}");

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

        $sessionId = $this->argument('pos_session_id');
        if ($sessionId !== null) {
            $session = PosSession::query()
                ->where('store_id', $store->getKey())
                ->whereKey((int) $sessionId)
                ->first();
        } else {
            $session = PosSession::query()
                ->where('store_id', $store->getKey())
                ->where('status', 'closed')
                ->whereNotNull('closing_data')
                ->orderByDesc('closed_at')
                ->get()
                ->first(function (PosSession $s): bool {
                    return is_array(data_get($s->closing_data, 'z_report_data'));
                });
        }

        if (! $session) {
            $this->error('No matching closed POS session with z_report_data found.');

            return self::FAILURE;
        }

        if ($session->status !== 'closed') {
            $this->error('Session must be closed.');

            return self::FAILURE;
        }

        $this->info("Syncing session #{$session->id} ({$session->session_number}) for {$store->name}…");

        $ok = $sync->sync($session->id, true);

        if (! $ok) {
            $run = \App\Models\PowerOfficeSyncRun::query()
                ->where('pos_session_id', $session->id)
                ->orderByDesc('id')
                ->first();
            $this->error($run?->error_message ?? 'Sync failed (see power_office_sync_runs).');

            return self::FAILURE;
        }

        $this->info('PowerOffice sync completed successfully (voucher + PDF when voucher Id was returned).');

        return self::SUCCESS;
    }
}
