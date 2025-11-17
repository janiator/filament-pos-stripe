<?php

namespace App\Console\Commands;

use App\Actions\Stores\SyncStoresFromStripe;
use Illuminate\Console\Command;

class SyncStripeConnectedAccounts extends Command
{
    protected $signature = 'stripe:sync-connected-accounts';

    protected $description = 'Sync Stripe connected accounts into Store models';

    public function handle(SyncStoresFromStripe $sync): int
    {
        $result = $sync();

        $this->info("Found {$result['total']} accounts. {$result['created']} created, {$result['updated']} updated.");

        return self::SUCCESS;
    }
}
