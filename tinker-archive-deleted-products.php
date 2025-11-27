<?php
// Run this in tinker: php artisan tinker < tinker-archive-deleted-products.php
// Or copy-paste into tinker

$store = \App\Models\Store::where('slug', 'jobberiet-as')->first();

if (!$store || !$store->stripe_account_id) {
    echo "Store not found or missing Stripe account ID\n";
    exit;
}

$secret = config('cashier.secret') ?? config('services.stripe.secret');
if (!$secret) {
    echo "Stripe secret not configured\n";
    exit;
}

$stripe = new \Stripe\StripeClient($secret);
$stripeAccountId = $store->stripe_account_id;

echo "Fetching products from Stripe for account: {$stripeAccountId}\n";

$archived = 0;
$skipped = 0;
$errors = 0;

try {
    $products = $stripe->products->all([
        'limit' => 100,
    ], [
        'stripe_account' => $stripeAccountId,
    ]);
    
    foreach ($products->autoPagingIterator() as $stripeProduct) {
        // Check if product exists locally
        $localProduct = \App\Models\ConnectedProduct::where('stripe_product_id', $stripeProduct->id)
            ->where('stripe_account_id', $stripeAccountId)
            ->first();
        
        // Check if any variant exists with this product ID
        $localVariant = \App\Models\ProductVariant::where('stripe_product_id', $stripeProduct->id)
            ->where('stripe_account_id', $stripeAccountId)
            ->first();
        
        // If product is already inactive in Stripe, skip
        if (!$stripeProduct->active) {
            $skipped++;
            continue;
        }
        
        // If product doesn't exist locally (deleted) or is inactive locally, archive it in Stripe
        if (!$localProduct && !$localVariant) {
            try {
                $stripe->products->update(
                    $stripeProduct->id,
                    ['active' => false],
                    ['stripe_account' => $stripeAccountId]
                );
                
                $archived++;
                echo "Archived product in Stripe: {$stripeProduct->name} ({$stripeProduct->id})\n";
            } catch (\Exception $e) {
                $errors++;
                echo "Failed to archive product {$stripeProduct->id}: {$e->getMessage()}\n";
            }
        } elseif ($localProduct && !$localProduct->active) {
            // Product exists locally but is inactive, archive in Stripe
            try {
                $stripe->products->update(
                    $stripeProduct->id,
                    ['active' => false],
                    ['stripe_account' => $stripeAccountId]
                );
                
                $archived++;
                echo "Archived inactive product in Stripe: {$stripeProduct->name} ({$stripeProduct->id})\n";
            } catch (\Exception $e) {
                $errors++;
                echo "Failed to archive product {$stripeProduct->id}: {$e->getMessage()}\n";
            }
        } else {
            $skipped++;
        }
    }
    
    echo "\nSummary:\n";
    echo "  Archived: {$archived}\n";
    echo "  Skipped: {$skipped}\n";
    echo "  Errors: {$errors}\n";
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

