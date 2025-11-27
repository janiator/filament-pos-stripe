<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class ArchiveDeletedProductsInStripe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:archive-deleted-products {store? : Store slug (e.g., jobberiet-as)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete or archive products in Stripe that have been deleted locally or are inactive';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeSlug = $this->argument('store') ?? 'jobberiet-as';
        
        $store = Store::where('slug', $storeSlug)->first();
        
        if (!$store) {
            $this->error("Store '{$storeSlug}' not found.");
            return 1;
        }
        
        if (!$store->stripe_account_id) {
            $this->error("Store '{$storeSlug}' does not have a Stripe account ID.");
            return 1;
        }
        
        $secret = config('cashier.secret') ?? config('services.stripe.secret');
        if (!$secret) {
            $this->error('Stripe secret key not configured.');
            return 1;
        }
        
        $stripe = new StripeClient($secret);
        $stripeAccountId = $store->stripe_account_id;
        
        $this->info("Fetching products from Stripe for account: {$stripeAccountId}");
        
        try {
            // Get all products from Stripe
            $products = $stripe->products->all([
                'limit' => 100,
            ], [
                'stripe_account' => $stripeAccountId,
            ]);
            
            $deleted = 0;
            $archived = 0;
            $skipped = 0;
            $errors = 0;
            
            foreach ($products->autoPagingIterator() as $stripeProduct) {
                // Check if product exists locally
                $localProduct = \App\Models\ConnectedProduct::where('stripe_product_id', $stripeProduct->id)
                    ->where('stripe_account_id', $stripeAccountId)
                    ->first();
                
                // Check if any variant exists with this product ID
                $localVariant = \App\Models\ProductVariant::where('stripe_product_id', $stripeProduct->id)
                    ->where('stripe_account_id', $stripeAccountId)
                    ->first();
                
                // If product doesn't exist locally (deleted) or is inactive locally, try to delete/archive it in Stripe
                if (!$localProduct && !$localVariant) {
                    try {
                        // Try to delete first
                        $stripe->products->delete(
                            $stripeProduct->id,
                            [],
                            ['stripe_account' => $stripeAccountId]
                        );
                        
                        $deleted++;
                        $status = $stripeProduct->active ? 'active' : 'archived';
                        $this->info("Deleted {$status} product from Stripe: {$stripeProduct->name} ({$stripeProduct->id})");
                        
                        Log::info('Deleted product from Stripe', [
                            'stripe_product_id' => $stripeProduct->id,
                            'stripe_account_id' => $stripeAccountId,
                            'product_name' => $stripeProduct->name,
                            'was_active' => $stripeProduct->active,
                        ]);
                    } catch (\Stripe\Exception\InvalidRequestException $e) {
                        // If deletion fails (product has been used), fall back to archiving
                        if (str_contains($e->getMessage(), 'has been used') || str_contains($e->getMessage(), 'cannot be deleted')) {
                            try {
                                $stripe->products->update(
                                    $stripeProduct->id,
                                    ['active' => false],
                                    ['stripe_account' => $stripeAccountId]
                                );
                                
                                $archived++;
                                $this->info("Archived product in Stripe (cannot delete - has been used): {$stripeProduct->name} ({$stripeProduct->id})");
                                
                                Log::info('Archived deleted product in Stripe (cannot delete)', [
                                    'stripe_product_id' => $stripeProduct->id,
                                    'stripe_account_id' => $stripeAccountId,
                                    'product_name' => $stripeProduct->name,
                                    'reason' => 'Product has been used',
                                ]);
                            } catch (\Exception $archiveError) {
                                $errors++;
                                $this->warn("Failed to archive product {$stripeProduct->id}: {$archiveError->getMessage()}");
                                Log::error('Failed to archive product in Stripe', [
                                    'stripe_product_id' => $stripeProduct->id,
                                    'stripe_account_id' => $stripeAccountId,
                                    'error' => $archiveError->getMessage(),
                                ]);
                            }
                        } else {
                            $errors++;
                            $this->warn("Failed to delete product {$stripeProduct->id}: {$e->getMessage()}");
                            Log::error('Failed to delete product from Stripe', [
                                'stripe_product_id' => $stripeProduct->id,
                                'stripe_account_id' => $stripeAccountId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $this->warn("Failed to delete/archive product {$stripeProduct->id}: {$e->getMessage()}");
                        Log::error('Failed to delete/archive product in Stripe', [
                            'stripe_product_id' => $stripeProduct->id,
                            'stripe_account_id' => $stripeAccountId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } elseif ($localProduct && !$localProduct->active) {
                    // Product exists locally but is inactive, try to delete/archive in Stripe
                    try {
                        // Try to delete first
                        $stripe->products->delete(
                            $stripeProduct->id,
                            [],
                            ['stripe_account' => $stripeAccountId]
                        );
                        
                        $deleted++;
                        $status = $stripeProduct->active ? 'active' : 'archived';
                        $this->info("Deleted {$status} inactive product from Stripe: {$stripeProduct->name} ({$stripeProduct->id})");
                    } catch (\Stripe\Exception\InvalidRequestException $e) {
                        // If deletion fails, fall back to archiving
                        if (str_contains($e->getMessage(), 'has been used') || str_contains($e->getMessage(), 'cannot be deleted')) {
                            try {
                                $stripe->products->update(
                                    $stripeProduct->id,
                                    ['active' => false],
                                    ['stripe_account' => $stripeAccountId]
                                );
                                
                                $archived++;
                                $this->info("Archived inactive product in Stripe (cannot delete): {$stripeProduct->name} ({$stripeProduct->id})");
                            } catch (\Exception $archiveError) {
                                $errors++;
                                $this->warn("Failed to archive product {$stripeProduct->id}: {$archiveError->getMessage()}");
                            }
                        } else {
                            $errors++;
                            $this->warn("Failed to delete product {$stripeProduct->id}: {$e->getMessage()}");
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $this->warn("Failed to delete/archive product {$stripeProduct->id}: {$e->getMessage()}");
                    }
                } else {
                    $skipped++;
                }
            }
            
            $this->info("\nSummary:");
            $this->info("  Deleted: {$deleted}");
            $this->info("  Archived: {$archived}");
            $this->info("  Skipped: {$skipped}");
            $this->info("  Errors: {$errors}");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('Error archiving deleted products in Stripe', [
                'store' => $storeSlug,
                'stripe_account_id' => $stripeAccountId,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }
    }
}

