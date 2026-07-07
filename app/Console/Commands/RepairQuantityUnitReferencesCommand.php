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
        $globalCount = QuantityUnit::query()->visibleInCatalog()->count();

        $this->info("Global active quantity units: {$globalCount}");
        $this->info("Products remapped: {$updated}");

        return self::SUCCESS;
    }
}
