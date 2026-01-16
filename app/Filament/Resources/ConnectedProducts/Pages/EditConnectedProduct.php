<?php

namespace App\Filament\Resources\ConnectedProducts\Pages;

use App\Filament\Resources\ConnectedProducts\ConnectedProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditConnectedProduct extends EditRecord
{
    protected static string $resource = ConnectedProductResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Ensure the product belongs to the current tenant
        $tenant = \Filament\Facades\Filament::getTenant();
        if ($tenant && $tenant->slug !== 'visivo-admin') {
            $product = $this->record;
            // Check via stripe_account_id since ConnectedProduct doesn't have store_id
            if ($product->stripe_account_id !== $tenant->stripe_account_id) {
                \Filament\Notifications\Notification::make()
                    ->title('Access Denied')
                    ->danger()
                    ->body('This product does not belong to your store.')
                    ->send();

                $this->redirect($this->getResource()::getUrl('index'));
            }
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle compare_at_price_decimal conversion
        if (isset($data['compare_at_price_decimal'])) {
            if ($data['compare_at_price_decimal'] !== null && $data['compare_at_price_decimal'] !== '') {
                $data['compare_at_price_amount'] = (int) round($data['compare_at_price_decimal'] * 100);
            } else {
                $data['compare_at_price_amount'] = null;
            }
            unset($data['compare_at_price_decimal']);
        }

        // If no_price_in_pos is enabled and price is empty, ensure it stays null
        if (!empty($data['no_price_in_pos']) && (empty($data['price']) || $data['price'] === '')) {
            $data['price'] = null;
            $data['default_price'] = null; // Also clear default_price to prevent restoration
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $product = $this->record;

        // Sync price if product has a price
        // Prices are always created in Stripe regardless of no_price_in_pos setting
        if (!$product->isVariable() 
            && $product->price 
            && $product->stripe_product_id 
            && $product->stripe_account_id) {
            $syncPriceAction = new \App\Actions\ConnectedPrices\SyncProductPrice();
            $syncPriceAction($product);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSkus')
                ->label('Generate SKUs')
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Generate SKUs for Variants')
                ->modalDescription('This will generate unique SKUs for all variants that don\'t have one. This action cannot be undone.')
                ->action('generateSkus'),
            DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Product')
                ->modalDescription(function () {
                    $product = $this->record;
                    $hasBeenUsed = $product->hasBeenUsedInPurchases();
                    
                    if ($hasBeenUsed) {
                        return 'This product has been used in purchases and cannot be deleted. It will be archived instead (set to inactive).';
                    }
                    
                    return 'Are you sure you want to delete this product? This will also delete all associated variants. This action cannot be undone.';
                })
                ->action(function () {
                    $product = $this->record;
                    $hasBeenUsed = $product->hasBeenUsedInPurchases();
                    
                    if ($hasBeenUsed) {
                        // Archive instead of delete
                        $this->archiveProduct($product);
                        
                        Notification::make()
                            ->warning()
                            ->title('Product Archived')
                            ->body('This product has been used in purchases and cannot be deleted. It has been archived instead (set to inactive).')
                            ->send();
                        
                        // Redirect to prevent actual deletion
                        $this->redirect($this->getResource()::getUrl('index'));
                    } else {
                        // Product hasn't been used, proceed with deletion
                        // The model's deleting event will handle variant deletion/archiving
                        $product->delete();
                        
                        Notification::make()
                            ->success()
                            ->title('Product Deleted')
                            ->body('The product and its variants have been deleted successfully.')
                            ->send();
                        
                        $this->redirect($this->getResource()::getUrl('index'));
                    }
                }),
        ];
    }

    protected function archiveProduct(ConnectedProduct $product): void
    {
        // Archive all variants first
        $variants = $product->variants()->get();
        foreach ($variants as $variant) {
            $variantHasBeenUsed = $variant->hasBeenUsedInPurchases();
            
            if ($variantHasBeenUsed) {
                // Archive variant instead of deleting
                $variant->active = false;
                $variant->saveQuietly();
                
                // Archive variant product in Stripe
                if ($variant->stripe_product_id && $variant->stripe_account_id) {
                    $secret = config('cashier.secret') ?? config('services.stripe.secret');
                    if ($secret) {
                        try {
                            $stripe = new \Stripe\StripeClient($secret);
                            $stripe->products->update(
                                $variant->stripe_product_id,
                                ['active' => false],
                                ['stripe_account' => $variant->stripe_account_id]
                            );
                        } catch (\Throwable $e) {
                            \Log::warning('Failed to archive variant product in Stripe', [
                                'variant_id' => $variant->id,
                                'stripe_product_id' => $variant->stripe_product_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } else {
                // Delete unused variant
                $variant->delete();
            }
        }
        
        // Archive product in Stripe
        if ($product->stripe_product_id && $product->stripe_account_id) {
            $deleteAction = new \App\Actions\ConnectedProducts\DeleteConnectedProductFromStripe();
            $deleteAction($product);
        }
        
        // Archive product locally
        $product->active = false;
        $product->saveQuietly();
    }

    public function generateSkus(): void
    {
        $product = $this->record;
        
        // Get variants without SKU for this product
        $variants = \App\Models\ProductVariant::where('connected_product_id', $product->id)
            ->where('stripe_account_id', $product->stripe_account_id)
            ->whereNull('sku')
            ->get();
        
        if ($variants->isEmpty()) {
            Notification::make()
                ->info()
                ->title('No variants need SKUs')
                ->body('All variants already have SKUs assigned.')
                ->send();
            return;
        }

        $generated = 0;
        foreach ($variants as $variant) {
            // Generate unique SKU based on product ID and variant options
            $skuParts = [
                $product->id,
                $variant->option1_value ?? '',
                $variant->option2_value ?? '',
                $variant->option3_value ?? '',
            ];
            $baseSku = 'PROD-' . $product->id . '-' . substr(md5(implode('-', array_filter($skuParts))), 0, 8);
            
            // Ensure uniqueness by checking if SKU already exists
            $sku = $baseSku;
            $counter = 1;
            while (\App\Models\ProductVariant::where('stripe_account_id', $variant->stripe_account_id)
                ->where('sku', $sku)
                ->where('id', '!=', $variant->id)
                ->exists()) {
                $sku = $baseSku . '-' . $counter;
                $counter++;
            }
            
            $variant->sku = $sku;
            $variant->saveQuietly();
            $generated++;
        }

        Notification::make()
            ->success()
            ->title('SKUs Generated')
            ->body("Successfully generated SKUs for {$generated} variant(s).")
            ->send();
    }
}
