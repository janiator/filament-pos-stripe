<?php

namespace App\Console\Commands;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\Store;
use App\Services\Tripletex\TripletexHistoricalSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TripletexSyncHistoricalCommand extends Command
{
    protected $signature = 'tripletex:sync-historical
        {store : Numeric store ID}
        {--type=z-report : z-report or payout}
        {--from= : Inclusive start date (Y-m-d) on closed_at (Z) or arrival_date (payout)}
        {--to= : Inclusive end date (Y-m-d)}
        {--limit=50 : Max jobs to queue (1–500)}
        {--all : Also queue rows that already have a successful Tripletex sync}';

    protected $description = 'Queue Tripletex Z-report or payout sync jobs for historical POS / Stripe mirror rows';

    public function handle(TripletexHistoricalSyncService $historical): int
    {
        $storeId = (int) $this->argument('store');
        $store = Store::query()->find($storeId);
        if (! $store) {
            $this->error("Store {$storeId} not found.");

            return self::FAILURE;
        }

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::Tripletex)) {
            $this->error('Tripletex add-on is not active for this store.');

            return self::FAILURE;
        }

        $from = $this->option('from') ? Carbon::parse((string) $this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to'))->endOfDay() : null;
        $limit = (int) $this->option('limit');
        $onlyMissing = ! (bool) $this->option('all');

        $type = strtolower(trim((string) $this->option('type')));
        if ($type === 'payout') {
            $result = $historical->queuePayouts($store, $from, $to, $limit, $onlyMissing);
            $this->info("Queued {$result['queued']} payout sync job(s).");

            return self::SUCCESS;
        }

        if (in_array($type, ['z-report', 'z_report', 'z'], true)) {
            $result = $historical->queueZReports($store, $from, $to, $limit, $onlyMissing);
            $this->info("Queued {$result['queued']} Z-report sync job(s), skipped {$result['skipped']} ineligible session(s).");

            return self::SUCCESS;
        }

        $this->error('Invalid --type (use z-report or payout).');

        return self::FAILURE;
    }
}
