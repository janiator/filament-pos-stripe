<?php

namespace App\Console\Commands;

use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Actions\ConnectedProducts\CreateVariantProductInStripe;
use App\Actions\ConnectedProducts\UpdateConnectedProductToStripe;
use App\Actions\ConnectedPrices\SyncProductPrice;
use App\Actions\ConnectedPrices\CreateConnectedPriceInStripe;
use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;

class SyncProductsToStripe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:sync-products-to-stripe 
                            {store? : Store slug (e.g., jobberiet-as). If not provided, syncs all stores with Stripe accounts}
                            {--update : Also update existing products in Stripe (products that already have stripe_product_id)}
                            {--skip-prices : Skip syncing prices to Stripe}
                            {--skip-variants : Skip syncing variant products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from local database to Stripe (create/update products in Stripe)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeSlug = $this->argument('store');
        $updateExisting = $this->option('update');
        $skipPrices = $this->option('skip-prices');
        $skipVariants = $this->option('skip-variants');
        
        $createAction = new CreateConnectedProductInStripe();
        $updateAction = new UpdateConnectedProductToStripe();
        $createVariantAction = new CreateVariantProductInStripe();
        $syncPriceAction = new SyncProductPrice();
        $createPriceAction = new CreateConnectedPriceInStripe();
        
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
            
            $this->info("Syncing products to Stripe for store: {$store->name} (ID: {$store->id})");
            $this->info("Stripe Account: {$store->stripe_account_id}\n");
            
            $result = $this->syncStoreProducts(
                $store,
                $createAction,
                $updateAction,
                $createVariantAction,
                $syncPriceAction,
                $createPriceAction,
                $updateExisting,
                $skipPrices,
                $skipVariants
            );
            
            $this->displayResults($result, $store->name);
            
            return self::SUCCESS;
        } else {
            // Sync all stores with Stripe accounts
            $stores = Store::whereNotNull('stripe_account_id')->get();
            
            if ($stores->isEmpty()) {
                $this->warn('No stores with Stripe accounts found.');
                return self::SUCCESS;
            }
            
            $this->info("Found {$stores->count()} store(s) with Stripe accounts. Syncing products to Stripe...\n");
            
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalVariantsCreated = 0;
            $totalPricesSynced = 0;
            $allErrors = [];
            
            foreach ($stores as $store) {
                $this->line("Syncing products for: {$store->name} ({$store->stripe_account_id})");
                
                $result = $this->syncStoreProducts(
                    $store,
                    $createAction,
                    $updateAction,
                    $createVariantAction,
                    $syncPriceAction,
                    $createPriceAction,
                    $updateExisting,
                    $skipPrices,
                    $skipVariants
                );
                
                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
                $totalVariantsCreated += $result['variants_created'];
                $totalPricesSynced += $result['prices_synced'];
                $allErrors = array_merge($allErrors, $result['errors']);
                
                $this->line("  → Created: {$result['created']}, Updated: {$result['updated']}, Variants: {$result['variants_created']}, Prices: {$result['prices_synced']}");
                
                if (!empty($result['errors'])) {
                    $this->warn("  → Errors: " . count($result['errors']));
                }
            }
            
            $this->newLine();
            $this->info("Summary across all stores:");
            $this->info("  Total created: {$totalCreated}");
            $this->info("  Total updated: {$totalUpdated}");
            $this->info("  Total variants created: {$totalVariantsCreated}");
            $this->info("  Total prices synced: {$totalPricesSynced}");
            
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
     * Sync products for a specific store
     */
    protected function syncStoreProducts(
        Store $store,
        CreateConnectedProductInStripe $createAction,
        UpdateConnectedProductToStripe $updateAction,
        CreateVariantProductInStripe $createVariantAction,
        SyncProductPrice $syncPriceAction,
        CreateConnectedPriceInStripe $createPriceAction,
        bool $updateExisting,
        bool $skipPrices,
        bool $skipVariants
    ): array {
        $result = [
            'created' => 0,
            'updated' => 0,
            'variants_created' => 0,
            'prices_synced' => 0,
            'errors' => [],
        ];
        
        // Find products without stripe_product_id (need to be created)
        $productsToCreate = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
            ->whereNull('stripe_product_id')
            ->get();
        
        $this->line("  Found {$productsToCreate->count()} product(s) without Stripe ID (will be created)");
        
        foreach ($productsToCreate as $product) {
            try {
                $stripeProductId = $createAction($product);
                
                if ($stripeProductId) {
                    $product->stripe_product_id = $stripeProductId;
                    $product->saveQuietly();
                    $result['created']++;
                    
                    // Sync price if not skipped
                    if (!$skipPrices && !$product->isVariable() && !$product->no_price_in_pos && $product->price) {
                        try {
                            $syncPriceAction($product);
                            $result['prices_synced']++;
                        } catch (\Throwable $e) {
                            $result['errors'][] = "Failed to sync price for product {$product->id}: {$e->getMessage()}";
                        }
                    }
                } else {
                    $result['errors'][] = "Failed to create product in Stripe: {$product->name} (ID: {$product->id})";
                }
            } catch (\Throwable $e) {
                $result['errors'][] = "Error creating product {$product->id}: {$e->getMessage()}";
            }
        }
        
        // Update existing products if requested
        if ($updateExisting) {
            $productsToUpdate = ConnectedProduct::where('stripe_account_id', $store->stripe_account_id)
                ->whereNotNull('stripe_product_id')
                ->get();
            
            $this->line("  Found {$productsToUpdate->count()} product(s) with Stripe ID (will be updated)");
            
            foreach ($productsToUpdate as $product) {
                try {
                    $updateAction($product);
                    $result['updated']++;
                    
                    // Sync price if not skipped
                    if (!$skipPrices && !$product->isVariable() && !$product->no_price_in_pos && $product->price) {
                        try {
                            $syncPriceAction($product);
                            $result['prices_synced']++;
                        } catch (\Throwable $e) {
                            $result['errors'][] = "Failed to sync price for product {$product->id}: {$e->getMessage()}";
                        }
                    }
                } catch (\Throwable $e) {
                    $result['errors'][] = "Error updating product {$product->id}: {$e->getMessage()}";
                }
            }
        }
        
        // Handle variants if not skipped
        if (!$skipVariants) {
            $variantsToCreate = ProductVariant::where('stripe_account_id', $store->stripe_account_id)
                ->whereNull('stripe_product_id')
                ->get();
            
            if ($variantsToCreate->isNotEmpty()) {
                $this->line("  Found {$variantsToCreate->count()} variant(s) without Stripe ID (will be created)");
            }
            
            foreach ($variantsToCreate as $variant) {
                try {
                    $stripeProductId = $createVariantAction($variant);
                    
                    if ($stripeProductId) {
                        $variant->stripe_product_id = $stripeProductId;
                        $variant->saveQuietly();
                        $result['variants_created']++;
                        
                        // Create price for variant if not skipped
                        if (!$skipPrices && !$variant->no_price_in_pos && $variant->price_amount && $variant->price_amount > 0) {
                            try {
                                $priceId = $createPriceAction(
                                    $stripeProductId,
                                    $variant->stripe_account_id,
                                    $variant->price_amount,
                                    $variant->currency ?? 'nok',
                                    [
                                        'nickname' => $variant->variant_name,
                                        'metadata' => [
                                            'source' => 'variant',
                                            'variant_id' => (string) $variant->id,
                                            'sku' => $variant->sku ?? '',
                                            'barcode' => $variant->barcode ?? '',
                                        ],
                                    ]
                                );
                                
                                if ($priceId) {
                                    $variant->stripe_price_id = $priceId;
                                    $variant->saveQuietly();
                                    $result['prices_synced']++;
                                }
                            } catch (\Throwable $e) {
                                $result['errors'][] = "Failed to create price for variant {$variant->id}: {$e->getMessage()}";
                            }
                        }
                    } else {
                        $result['errors'][] = "Failed to create variant in Stripe: {$variant->full_title} (ID: {$variant->id})";
                    }
                } catch (\Throwable $e) {
                    $result['errors'][] = "Error creating variant {$variant->id}: {$e->getMessage()}";
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Display sync results for a single store
     */
    protected function displayResults(array $result, string $storeName): void
    {
        $this->newLine();
        $this->info("Sync completed for {$storeName}:");
        $this->info("  Created: {$result['created']}");
        $this->info("  Updated: {$result['updated']}");
        $this->info("  Variants created: {$result['variants_created']}");
        $this->info("  Prices synced: {$result['prices_synced']}");
        
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




