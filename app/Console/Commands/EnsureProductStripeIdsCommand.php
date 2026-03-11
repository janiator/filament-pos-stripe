<?php

namespace App\Console\Commands;

use App\Jobs\EnsureProductStripeIdJob;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Console\Command;

class EnsureProductStripeIdsCommand extends Command
{
    protected $signature = 'products:ensure-stripe-ids
                            {store? : Store slug or ID. If omitted, process all stores with Stripe accounts}
                            {--sync : Run synchronously instead of queueing jobs}
                            {--verify-all : Also verify products that already have a Stripe ID (check they exist on the connected account)}';

    protected $description = 'Queue (or run) checks to ensure products have valid Stripe IDs on the connected account; recreate if missing';

    public function handle(): int
    {
        $storeSlug = $this->argument('store');
        $sync = $this->option('sync');
        $verifyAll = $this->option('verify-all');

        $stores = $storeSlug
            ? $this->resolveStore($storeSlug)
            : Store::whereNotNull('stripe_account_id')->get();

        if ($stores->isEmpty()) {
            $this->warn('No store(s) found.');

            return self::FAILURE;
        }

        foreach ($stores as $store) {
            $query = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id);

            if (! $verifyAll) {
                $query->where(function ($q) {
                    $q->whereNull('stripe_product_id')
                        ->orWhereNull('default_price');
                });
            }

            $products = $query->pluck('id');
            $count = $products->count();

            if ($count === 0) {
                $this->line("Store <info>{$store->name}</info>: no products to process.");

                continue;
            }

            $this->info("Store <info>{$store->name}</info>: {$count} product(s) to process (".($sync ? 'sync' : 'queued').').');

            foreach ($products as $productId) {
                if ($sync) {
                    EnsureProductStripeIdJob::dispatchSync($productId);
                } else {
                    EnsureProductStripeIdJob::dispatch($productId);
                }
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Store> */
    private function resolveStore(string $storeSlug): \Illuminate\Support\Collection
    {
        $store = Store::where('slug', $storeSlug)->first();
        if (! $store && is_numeric($storeSlug)) {
            $store = Store::find($storeSlug);
        }
        if (! $store) {
            return collect();
        }
        if (! $store->stripe_account_id) {
            $this->warn("Store {$store->name} has no Stripe account.");

            return collect();
        }

        return collect([$store]);
    }
}
