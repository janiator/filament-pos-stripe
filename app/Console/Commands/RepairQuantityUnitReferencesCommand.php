<?php

namespace App\Console\Commands;

use App\Models\QuantityUnit;
use Illuminate\Console\Command;

class RepairQuantityUnitReferencesCommand extends Command
{
    protected $signature = 'quantity-units:repair';

    protected $description = 'Seed global quantity units and remap products to selectable global units';

    public function handle(): int
    {
        $updated = QuantityUnit::remapLegacyProductReferences();
        $globalCount = QuantityUnit::query()
            ->whereNull('store_id')
            ->whereNull('stripe_account_id')
            ->where('active', true)
            ->count();

        $this->info("Global active quantity units: {$globalCount}");
        $this->info("Products remapped: {$updated}");

        return self::SUCCESS;
    }
}
