<?php

namespace App\Console\Commands;

use App\Models\ConnectedProduct;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class FixVariantProducts extends Command
{
    protected $signature = 'products:fix-variants 
                            {--store= : Store ID or stripe_account_id to fix}
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix variant products that were incorrectly synced as main products';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $storeFilter = $this->option('store');

        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            $this->error('Stripe secret key not configured');
            return self::FAILURE;
        }

        $stripe = new StripeClient($secret);

        // Get stores to process
        $stores = Store::whereNotNull('stripe_account_id')
            ->where('stripe_account_id', '!=', '')
            ->get();

        if ($storeFilter) {
            $stores = $stores->filter(function ($store) use ($storeFilter) {
                return $store->id == $storeFilter || 
                       $store->stripe_account_id == $storeFilter;
            });
        }

        if ($stores->isEmpty()) {
            $this->warn('No stores found to process');
            return self::FAILURE;
        }

        $this->info("Processing {$stores->count()} store(s)...");
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $totalFixed = 0;
        $totalErrors = 0;

        foreach ($stores as $store) {
            $this->info("\nProcessing store: {$store->name} ({$store->stripe_account_id})");

            try {
                // Get all products from Stripe for this store
                $stripeProducts = $stripe->products->all(
                    ['limit' => 100],
                    ['stripe_account' => $store->stripe_account_id]
                );

                foreach ($stripeProducts->autoPagingIterator() as $stripeProduct) {
                    $metadata = $stripeProduct->metadata ? (array) $stripeProduct->metadata : [];
                    
                    // Check if this is a variant product
                    $isVariant = isset($metadata['variant_id']) || 
                                 (isset($metadata['source']) && in_array($metadata['source'], ['pos-variant', 'variant']));

                    if (!$isVariant) {
                        continue; // Not a variant, skip
                    }

                    // Check if it exists as a ConnectedProduct (incorrectly)
                    $incorrectProduct = ConnectedProduct::where('stripe_product_id', $stripeProduct->id)
                        ->where('stripe_account_id', $store->stripe_account_id)
                        ->first();

                    if (!$incorrectProduct) {
                        continue; // Not incorrectly synced, skip
                    }

                    // Check if it already exists as a ProductVariant (correctly)
                    $existingVariant = ProductVariant::where('stripe_product_id', $stripeProduct->id)
                        ->where('stripe_account_id', $store->stripe_account_id)
                        ->first();

                    if ($existingVariant) {
                        $this->line("  ✓ Variant {$stripeProduct->id} already exists correctly, skipping");
                        continue;
                    }

                    // Find parent product
                    $parentProductId = $metadata['parent_product_id'] ?? null;
                    $parentStripeProductId = $metadata['parent_stripe_product_id'] ?? null;

                    $parentProduct = null;
                    if ($parentProductId) {
                        $parentProduct = ConnectedProduct::where('id', $parentProductId)
                            ->where('stripe_account_id', $store->stripe_account_id)
                            ->first();
                    }
                    
                    if (!$parentProduct && $parentStripeProductId) {
                        $parentProduct = ConnectedProduct::where('stripe_product_id', $parentStripeProductId)
                            ->where('stripe_account_id', $store->stripe_account_id)
                            ->first();
                    }

                    if (!$parentProduct) {
                        $this->warn("  ⚠ Variant {$stripeProduct->id} has no parent product, skipping");
                        $totalErrors++;
                        continue;
                    }

                    // Get price
                    $priceId = $stripeProduct->default_price;
                    $priceAmount = null;
                    $currency = 'nok';
                    if ($priceId) {
                        try {
                            $price = $stripe->prices->retrieve($priceId, [], ['stripe_account' => $store->stripe_account_id]);
                            $priceAmount = $price->unit_amount;
                            $currency = $price->currency ?? 'nok';
                        } catch (\Throwable $e) {
                            Log::warning('Failed to retrieve variant price', [
                                'variant_product_id' => $stripeProduct->id,
                                'price_id' => $priceId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    if ($dryRun) {
                        $this->info("  [DRY RUN] Would move variant {$stripeProduct->id} to ProductVariant");
                        $this->line("    Parent: {$parentProduct->name} (ID: {$parentProduct->id})");
                        $this->line("    Options: " . ($metadata['option1_value'] ?? 'N/A') . 
                                   ($metadata['option2_value'] ? ' / ' . $metadata['option2_value'] : '') .
                                   ($metadata['option3_value'] ? ' / ' . $metadata['option3_value'] : ''));
                        $totalFixed++;
                    } else {
                        // Create ProductVariant
                        $variant = ProductVariant::create([
                            'connected_product_id' => $parentProduct->id,
                            'stripe_account_id' => $store->stripe_account_id,
                            'stripe_product_id' => $stripeProduct->id,
                            'stripe_price_id' => $priceId,
                            'option1_name' => $metadata['option1_name'] ?? null,
                            'option1_value' => $metadata['option1_value'] ?? null,
                            'option2_name' => $metadata['option2_name'] ?? null,
                            'option2_value' => $metadata['option2_value'] ?? null,
                            'option3_name' => $metadata['option3_name'] ?? null,
                            'option3_value' => $metadata['option3_value'] ?? null,
                            'sku' => $metadata['sku'] ?? null,
                            'barcode' => $metadata['barcode'] ?? null,
                            'price_amount' => $priceAmount,
                            'currency' => $currency,
                            'requires_shipping' => isset($stripeProduct->shippable) ? (bool) $stripeProduct->shippable : true,
                            'taxable' => true,
                            'active' => $stripeProduct->active,
                            'image_url' => !empty($stripeProduct->images) ? $stripeProduct->images[0] : null,
                            'metadata' => $metadata,
                        ]);

                        // Delete the incorrect ConnectedProduct
                        $incorrectProduct->delete();

                        $this->info("  ✓ Fixed variant {$stripeProduct->id} - moved to ProductVariant");
                        $totalFixed++;
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Error processing store {$store->name}: {$e->getMessage()}");
                $totalErrors++;
                Log::error('Error fixing variant products', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry run complete. Would fix {$totalFixed} variant(s).");
        } else {
            $this->info("Fixed {$totalFixed} variant(s).");
        }
        
        if ($totalErrors > 0) {
            $this->warn("Encountered {$totalErrors} error(s).");
        }

        return self::SUCCESS;
    }
}

