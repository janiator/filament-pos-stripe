<?php

namespace App\Console\Commands;

use App\Actions\ConnectedCharges\SyncConnectedChargesFromStripe;
use App\Models\Store;
use Illuminate\Console\Command;

class SyncStripeCharges extends Command
{
    protected $signature = 'stripe:sync-charges {store? : Store slug or ID. If not provided, syncs all stores with Stripe accounts}';

    protected $description = 'Sync charges from Stripe connected accounts into ConnectedCharge models (updates captured, refunded, status, etc.)';

    public function handle(SyncConnectedChargesFromStripe $syncAction): int
    {
        $storeSlug = $this->argument('store');

        if ($storeSlug) {
            $store = Store::where('slug', $storeSlug)->first();

            if (! $store && is_numeric($storeSlug)) {
                $store = Store::find($storeSlug);
            }

            if (! $store) {
                $this->error("Store '{$storeSlug}' not found.");

                return self::FAILURE;
            }

            if (! $store->stripe_account_id) {
                $this->error("Store '{$store->name}' does not have a Stripe account ID.");

                return self::FAILURE;
            }

            $this->info("Syncing charges for store: {$store->name} (ID: {$store->id})");

            $result = $syncAction($store, false);

            $this->displayResults($result, $store->name);

            return self::SUCCESS;
        }

        $stores = Store::whereNotNull('stripe_account_id')->get();

        if ($stores->isEmpty()) {
            $this->warn('No stores with Stripe accounts found.');

            return self::SUCCESS;
        }

        $this->info("Found {$stores->count()} store(s) with Stripe accounts. Syncing charges...\n");

        $totalFound = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $allErrors = [];

        foreach ($stores as $store) {
            $this->line("Syncing charges for: {$store->name} ({$store->stripe_account_id})");

            $result = $syncAction($store, false);

            $totalFound += $result['total'];
            $totalCreated += $result['created'];
            $totalUpdated += $result['updated'];
            $allErrors = array_merge($allErrors, $result['errors']);

            $this->line("  → Found: {$result['total']}, Created: {$result['created']}, Updated: {$result['updated']}");

            if (! empty($result['errors'])) {
                $this->warn('  → Errors: '.count($result['errors']));
            }
        }

        $this->newLine();
        $this->info('Summary across all stores:');
        $this->info("  Total found: {$totalFound}");
        $this->info("  Total created: {$totalCreated}");
        $this->info("  Total updated: {$totalUpdated}");

        if (! empty($allErrors)) {
            $this->warn('  Total errors: '.count($allErrors));
            if ($this->option('verbose')) {
                foreach ($allErrors as $error) {
                    $this->error("    - {$error}");
                }
            }
        }

        return self::SUCCESS;
    }

    protected function displayResults(array $result, string $storeName): void
    {
        $this->newLine();
        $this->info("Sync completed for {$storeName}:");
        $this->info("  Found: {$result['total']}");
        $this->info("  Created: {$result['created']}");
        $this->info("  Updated: {$result['updated']}");

        if (! empty($result['errors'])) {
            $this->warn('  Errors: '.count($result['errors']));
            if ($this->option('verbose')) {
                foreach ($result['errors'] as $error) {
                    $this->error("    - {$error}");
                }
            }
        }
    }
}
