<?php

namespace App\Console\Commands;

use App\Actions\ConnectedProducts\SyncConnectedProductsFromStripe;
use App\Models\Store;
use Illuminate\Console\Command;

class SyncStripeProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:sync-products {store? : Store slug (e.g., jobberiet-as). If not provided, syncs all stores with Stripe accounts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Stripe connected accounts into ConnectedProduct models';

    /**
     * Execute the console command.
     */
    public function handle(SyncConnectedProductsFromStripe $syncAction): int
    {
        $storeSlug = $this->argument('store');
        
        if ($storeSlug) {
            // Sync specific store
            $store = Store::where('slug', $storeSlug)->first();
            
            if (!$store) {
                // Try ID if slug not found
                if (is_numeric($storeSlug)) {
                    $store = Store::find($storeSlug);
                }
            }
            
            if (!$store) {
                $this->error("Store '{$storeSlug}' not found.");
                return self::FAILURE;
            }
            
            if (!$store->stripe_account_id) {
                $this->error("Store '{$store->name}' does not have a Stripe account ID.");
                return self::FAILURE;
            }
            
            $this->info("Syncing products for store: {$store->name} (ID: {$store->id})");
            $this->info("Stripe Account: {$store->stripe_account_id}");
            
            $result = $syncAction($store, false);
            
            $this->displayResults($result, $store->name);
            
            return self::SUCCESS;
        } else {
            // Sync all stores with Stripe accounts
            $stores = Store::whereNotNull('stripe_account_id')->get();
            
            if ($stores->isEmpty()) {
                $this->warn('No stores with Stripe accounts found.');
                return self::SUCCESS;
            }
            
            $this->info("Found {$stores->count()} store(s) with Stripe accounts. Syncing products...\n");
            
            $totalFound = 0;
            $totalCreated = 0;
            $totalUpdated = 0;
            $allErrors = [];
            
            foreach ($stores as $store) {
                $this->line("Syncing products for: {$store->name} ({$store->stripe_account_id})");
                
                $result = $syncAction($store, false);
                
                $totalFound += $result['total'];
                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
                $allErrors = array_merge($allErrors, $result['errors']);
                
                $this->line("  → Found: {$result['total']}, Created: {$result['created']}, Updated: {$result['updated']}");
                
                if (!empty($result['errors'])) {
                    $this->warn("  → Errors: " . count($result['errors']));
                }
            }
            
            $this->newLine();
            $this->info("Summary across all stores:");
            $this->info("  Total found: {$totalFound}");
            $this->info("  Total created: {$totalCreated}");
            $this->info("  Total updated: {$totalUpdated}");
            
            if (!empty($allErrors)) {
                $this->warn("  Total errors: " . count($allErrors));
                if ($this->option('verbose')) {
                    foreach ($allErrors as $error) {
                        $this->error("    - {$error}");
                    }
                }
            }
            
            return self::SUCCESS;
        }
    }
    
    /**
     * Display sync results for a single store
     */
    protected function displayResults(array $result, string $storeName): void
    {
        $this->newLine();
        $this->info("Sync completed for {$storeName}:");
        $this->info("  Found: {$result['total']}");
        $this->info("  Created: {$result['created']}");
        $this->info("  Updated: {$result['updated']}");
        
        if (!empty($result['errors'])) {
            $this->warn("  Errors: " . count($result['errors']));
            if ($this->option('verbose')) {
                foreach ($result['errors'] as $error) {
                    $this->error("    - {$error}");
                }
            }
        }
    }
}

