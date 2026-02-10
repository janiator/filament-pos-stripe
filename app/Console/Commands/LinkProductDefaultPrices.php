<?php

namespace App\Console\Commands;

use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Console\Command;

class LinkProductDefaultPrices extends Command
{
    protected $signature = 'products:link-default-prices
                            {--store= : Store ID, slug, or stripe_account_id to limit to one store}
                            {--dry-run : Do not save; only report how many would be saved}';

    protected $description = 'Save every product again (triggers model events e.g. SyncProductPrice). No Stripe backfill.';

    public function handle(): int
    {
        $storeFilter = $this->option('store');
        $dryRun = $this->option('dry-run');

        $query = ConnectedProduct::query()
            ->whereNotNull('stripe_product_id')
            ->where('stripe_product_id', '!=', '')
            ->whereNotNull('stripe_account_id');

        if ($storeFilter) {
            $storeQuery = Store::query()->where('slug', $storeFilter)
                ->orWhere('stripe_account_id', $storeFilter);
            if (is_numeric($storeFilter)) {
                $storeQuery->orWhere('id', (int) $storeFilter);
            }
            $store = $storeQuery->first();
            if (! $store) {
                $this->error("Store '{$storeFilter}' not found.");

                return self::FAILURE;
            }
            $query->where('stripe_account_id', $store->stripe_account_id);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->warn('No products with Stripe link found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN – no changes will be written.');
            $this->info("Would save {$products->count()} product(s).");

            return self::SUCCESS;
        }

        $saved = 0;
        foreach ($products as $product) {
            $product->save();
            $saved++;
        }

        $this->info("Saved {$saved} product(s).");

        return self::SUCCESS;
    }
}
