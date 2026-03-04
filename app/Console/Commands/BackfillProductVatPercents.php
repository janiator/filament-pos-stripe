<?php

namespace App\Console\Commands;

use App\Actions\ConnectedProducts\ResolveProductVatRate;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Console\Command;

class BackfillProductVatPercents extends Command
{
    protected $signature = 'products:backfill-vat-percents
                            {store? : Store slug (tenant). If omitted, run for all stores with Stripe accounts}
                            {--dry-run : Show what would be updated without saving}';

    protected $description = 'Backfill vat_percent on products that have it null (from article group code or tax_code)';

    public function handle(ResolveProductVatRate $resolveVat): int
    {
        $storeSlug = $this->argument('store');
        $dryRun = $this->option('dry-run');

        $stores = $this->resolveStores($storeSlug);
        if ($stores->isEmpty()) {
            $this->warn('No store(s) found.');

            return self::FAILURE;
        }

        $totalUpdated = 0;

        foreach ($stores as $store) {
            if (! $store->stripe_account_id) {
                $this->line("Skipping {$store->name}: no stripe_account_id.");

                continue;
            }

            $products = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
                ->whereNull('vat_percent')
                ->get();

            if ($products->isEmpty()) {
                $this->line("{$store->name}: no products with null vat_percent.");

                continue;
            }

            $this->info("{$store->name}: {$products->count()} product(s) with null vat_percent.");

            foreach ($products as $product) {
                $rate = $resolveVat($product);
                $vatPercent = round($rate * 100, 2);

                if ($dryRun) {
                    $this->line("  [dry-run] Would set product id={$product->id} ({$product->name}) vat_percent={$vatPercent}%");
                } else {
                    $product->vat_percent = $vatPercent;
                    $product->saveQuietly();
                }

                $totalUpdated++;
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry run: would update {$totalUpdated} product(s). Run without --dry-run to apply."
            : "Updated {$totalUpdated} product(s).");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Store>
     */
    private function resolveStores(?string $storeSlug): \Illuminate\Database\Eloquent\Collection
    {
        if ($storeSlug) {
            $store = Store::where('slug', $storeSlug)->first();
            if (! $store && is_numeric($storeSlug)) {
                $store = Store::find($storeSlug);
            }

            return $store ? Store::where('id', $store->id)->get() : new \Illuminate\Database\Eloquent\Collection;
        }

        return Store::whereNotNull('stripe_account_id')->get();
    }
}
