<?php

namespace App\Filament\Resources\PosPurchases\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class PosPurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Prominent header with key information
                Section::make('Purchase Overview')
                    ->schema([
                        TextEntry::make('formatted_amount')
                            ->label('Total Amount')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success')
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Payment Status')
                            ->badge()
                            ->colors([
                                'success' => 'succeeded',
                                'warning' => 'pending',
                                'danger' => ['failed', 'refunded'],
                                'info' => 'processing',
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->size(TextSize::Large),

                        TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : null)
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'cash' => 'success',
                                'card_present' => 'info',
                                default => 'gray',
                            })
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->size(TextSize::Large),

                        TextEntry::make('paid_at')
                            ->label('Transaction Date & Time')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->size(TextSize::Large)
                            ->color('gray'),

                        TextEntry::make('charge_display')
                            ->label('Transaction ID')
                            ->formatStateUsing(function ($record) {
                                if ($record->stripe_charge_id) {
                                    return $record->stripe_charge_id;
                                }
                                return 'Cash Payment #' . $record->id;
                            })
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag)
                            ->size(TextSize::Small)
                            ->color('gray'),
                    ])
                    ->columns(2)
                    ->icon(Heroicon::OutlinedShoppingCart),

                // Customer information (if available) - More prominent
                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Customer Name')
                            ->icon(Heroicon::OutlinedUser)
                            ->badge()
                            ->color('info')
                            ->size(TextSize::Large)
                            ->url(fn ($record) => $record->customer
                                ? \App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource::getUrl('view', ['record' => $record->customer])
                                : null)
                            ->visible(fn ($record) => $record->customer),

                        TextEntry::make('metadata.customer_name')
                            ->label('Customer Name')
                            ->icon(Heroicon::OutlinedUser)
                            ->badge()
                            ->color('info')
                            ->size(TextSize::Large)
                            ->visible(fn ($record) => !$record->customer && ($record->metadata['customer_name'] ?? null)),

                        TextEntry::make('customer.email')
                            ->label('Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable()
                            ->size(TextSize::Large)
                            ->visible(fn ($record) => $record->customer && $record->customer->email),
                    ])
                    ->columns(2)
                    ->icon(Heroicon::OutlinedUser)
                    ->visible(fn ($record) => $record->customer || ($record->metadata['customer_name'] ?? null)),

                // Purchase Note (if available)
                Section::make('Purchase Note')
                    ->schema([
                        TextEntry::make('metadata.note')
                            ->label('Note')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->placeholder('No note')
                            ->wrap()
                            ->columnSpanFull(),
                    ])
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->visible(fn ($record) => !empty($record->metadata['note'] ?? null)),

                // Receipt and Session Information
                Section::make('Receipt & Session')
                    ->schema([
                        TextEntry::make('receipt.receipt_number')
                            ->label('Receipt Number')
                            ->badge()
                            ->color('primary')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->size(TextSize::Large)
                            ->url(fn ($record) => $record->receipt
                                ? \App\Filament\Resources\Receipts\ReceiptResource::getUrl('preview', ['record' => $record->receipt])
                                : null)
                            ->placeholder('No receipt generated'),

                        TextEntry::make('posSession.session_number')
                            ->label('POS Session')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->url(fn ($record) => $record->posSession
                                ? \App\Filament\Resources\PosSessions\PosSessionResource::getUrl('view', ['record' => $record->posSession])
                                : null)
                            ->placeholder('No session'),

                        TextEntry::make('posSession.user.name')
                            ->label('Cashier')
                            ->icon(Heroicon::OutlinedUser)
                            ->badge()
                            ->color('gray')
                            ->visible(fn ($record) => $record->posSession && $record->posSession->user),

                        TextEntry::make('posSession.status')
                            ->label('Session Status')
                            ->badge()
                            ->colors([
                                'success' => 'open',
                                'gray' => 'closed',
                            ])
                            ->icon(Heroicon::OutlinedCircleStack)
                            ->visible(fn ($record) => $record->posSession),
                    ])
                    ->columns(2)
                    ->icon(Heroicon::OutlinedDocumentText),

                // Cart Items - Clean list display using stored product snapshots
                Section::make('Items Purchased')
                    ->schema([
                        TextEntry::make('items_display')
                            ->label('')
                            ->state(function ($record) {
                                // Get metadata directly - it's already cast as array by the model
                                $metadata = $record->metadata ?? [];
                                
                                // Clean items array if it exists (remove null byte keys)
                                $items = [];
                                if (isset($metadata['items']) && is_array($metadata['items'])) {
                                    foreach ($metadata['items'] as $item) {
                                        if (is_array($item)) {
                                            $cleanedItem = [];
                                            foreach ($item as $key => $value) {
                                                // Skip keys with null bytes
                                                if (strpos($key, "\0") === false) {
                                                    $cleanedItem[$key] = $value;
                                                }
                                            }
                                            $items[] = $cleanedItem;
                                        }
                                    }
                                }

                                if (empty($items)) {
                                    return 'No items in this purchase';
                                }

                                $lines = [];
                                
                                foreach ($items as $index => $item) {
                                    $quantity = $item['quantity'] ?? 1;
                                    $unitPrice = ($item['unit_price'] ?? 0) / 100;
                                    $discountAmount = ($item['discount_amount'] ?? 0) / 100;
                                    $originalPrice = isset($item['original_price']) ? ($item['original_price'] / 100) : null;
                                    
                                    // Calculate line total
                                    $lineTotal = ($unitPrice * $quantity) - ($discountAmount * $quantity);

                                    // Use stored product snapshot (preserves historical data)
                                    $productName = $item['product_name'] ?? null;
                                    
                                    // Fallback to fetching product if snapshot not available (old purchases)
                                    if (!$productName) {
                                        $productId = $item['product_id'] ?? null;
                                        $variantId = $item['variant_id'] ?? null;
                                        
                                        if ($variantId) {
                                            // Try to get variant first
                                            $variant = \App\Models\ProductVariant::find($variantId);
                                            if ($variant && $variant->product) {
                                                $productName = $variant->product->name;
                                                if ($variant->variant_name !== 'Default') {
                                                    $productName .= ' - ' . $variant->variant_name;
                                                }
                                            } elseif ($productId) {
                                                $product = \App\Models\ConnectedProduct::find($productId);
                                                $productName = $product ? $product->name : "Product #{$productId}";
                                            }
                                        } elseif ($productId) {
                                            $product = \App\Models\ConnectedProduct::find($productId);
                                            $productName = $product ? $product->name : "Product #{$productId}";
                                        }
                                        
                                        // Final fallback
                                        if (!$productName) {
                                            $productName = 'Unknown Product';
                                        }
                                    }

                                    // Build item line
                                    $line = "• {$quantity}x {$productName}";
                                    
                                    // Add product code if available (from snapshot or item)
                                    $productCode = $item['product_code'] ?? $item['article_group_code'] ?? null;
                                    if ($productCode) {
                                        $line .= " [{$productCode}]";
                                    }
                                    
                                    // Add variant info if available
                                    $variantId = $item['variant_id'] ?? null;
                                    if ($variantId) {
                                        $line .= " (Variant #{$variantId})";
                                    }
                                    
                                    // Add pricing info
                                    if ($originalPrice && $originalPrice > $unitPrice) {
                                        // Show original price if discounted
                                        $line .= "\n  " . number_format($originalPrice, 2) . ' NOK (original)';
                                        $line .= " → " . number_format($unitPrice, 2) . ' NOK (after discount)';
                                    } else {
                                        $line .= "\n  " . number_format($unitPrice, 2) . ' NOK';
                                    }
                                    
                                    $line .= " × {$quantity}";
                                    
                                    // Add discount if applicable
                                    if ($discountAmount > 0) {
                                        $line .= " (Discount: -" . number_format($discountAmount, 2) . ' NOK per unit)';
                                    }
                                    
                                    // Add total
                                    $line .= " = " . number_format($lineTotal, 2) . ' NOK';

                                    $lines[] = $line;
                                }

                                return implode("\n\n", $lines);
                            })
                            ->listWithLineBreaks()
                            ->placeholder('No items')
                            ->columnSpanFull(),
                    ])
                    ->icon(Heroicon::OutlinedShoppingBag),

                // Purchase Summary with VAT Breakdown
                Section::make('Purchase Summary')
                    ->schema([
                        TextEntry::make('subtotal_excluding_tax')
                            ->label('Subtotal (Excluding VAT)')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                
                                // Try to get values from metadata (in øre)
                                $subtotal = isset($metadata['subtotal']) && $metadata['subtotal'] > 0
                                    ? (int) $metadata['subtotal'] 
                                    : null;
                                $totalTax = isset($metadata['total_tax']) && $metadata['total_tax'] > 0
                                    ? (int) $metadata['total_tax'] 
                                    : null;
                                
                                // If metadata values are missing or 0, calculate from items
                                if (($subtotal === null || $subtotal <= 0) || ($totalTax === null || $totalTax <= 0)) {
                                    $items = $metadata['items'] ?? [];
                                    $calculatedSubtotal = 0;
                                    $calculatedTax = 0;
                                    
                                    foreach ($items as $item) {
                                        if (!is_array($item)) {
                                            continue;
                                        }
                                        
                                        $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
                                        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                                        $discountAmount = isset($item['discount_amount']) ? (int) $item['discount_amount'] : 0;
                                        
                                        $lineTotal = ($unitPrice * $quantity) - ($discountAmount * $quantity);
                                        $calculatedSubtotal += $lineTotal;
                                        
                                        // Calculate tax for this item
                                        // Try to get VAT percent from multiple sources
                                        $vatPercent = null;
                                        
                                        if (isset($item['vat_percent'])) {
                                            $vatPercent = (float) $item['vat_percent'];
                                        } elseif (isset($item['article_group_code'])) {
                                            $vatPercent = \App\Services\SafTCodeMapper::getVatPercentFromArticleGroupCode($item['article_group_code']);
                                        } elseif (isset($item['product_id'])) {
                                            // Fetch product from database
                                            try {
                                                $product = \App\Models\ConnectedProduct::find($item['product_id']);
                                                if ($product) {
                                                    if ($product->vat_percent) {
                                                        $vatPercent = (float) $product->vat_percent;
                                                    } elseif ($product->article_group_code) {
                                                        $vatPercent = \App\Services\SafTCodeMapper::getVatPercentFromArticleGroupCode($product->article_group_code);
                                                    }
                                                }
                                            } catch (\Exception $e) {
                                                // Product might be deleted, continue with default
                                            }
                                        }
                                        
                                        // Default to 25% if no VAT info found
                                        if ($vatPercent === null) {
                                            $vatPercent = 25.0;
                                        }
                                        
                                        // Tax-inclusive: Tax = Total × (Rate / (100 + Rate))
                                        $itemTax = (int) round($lineTotal * ($vatPercent / (100 + $vatPercent)));
                                        $calculatedTax += $itemTax;
                                    }
                                    
                                    // Apply cart-level discounts
                                    $cartDiscounts = isset($metadata['total_discounts']) 
                                        ? (int) $metadata['total_discounts'] 
                                        : 0;
                                    
                                    $subtotal = $calculatedSubtotal > 0 ? $calculatedSubtotal : ($record->amount - $cartDiscounts);
                                    $totalTax = $calculatedTax > 0 ? $calculatedTax : 0;
                                }
                                
                                // Calculate subtotal excluding tax
                                $subtotalExcludingTax = $subtotal - $totalTax;
                                
                                return number_format($subtotalExcludingTax / 100, 2) . ' NOK';
                            })
                            ->size(TextSize::Large)
                            ->icon(Heroicon::OutlinedCalculator),

                        TextEntry::make('discounts_display')
                            ->label('Discounts')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $discounts = ($metadata['total_discounts'] ?? 0) / 100;
                                if ($discounts <= 0) {
                                    return null;
                                }
                                return '-' . number_format($discounts, 2) . ' NOK';
                            })
                            ->badge()
                            ->color('success')
                            ->icon(Heroicon::OutlinedTag)
                            ->visible(fn ($record) => ($record->metadata['total_discounts'] ?? 0) > 0),

                        // VAT Breakdown Section
                        TextEntry::make('vat_breakdown')
                            ->label('VAT Breakdown')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $items = $metadata['items'] ?? [];
                                
                                // If no items, try to calculate from total amount
                                if (empty($items) || !is_array($items)) {
                                    $totalTax = isset($metadata['total_tax']) && $metadata['total_tax'] > 0
                                        ? (int) $metadata['total_tax'] 
                                        : null;
                                    
                                    // If no tax in metadata, calculate from total amount (assuming 25% VAT)
                                    if ($totalTax === null || $totalTax <= 0) {
                                        $totalAmount = $record->amount;
                                        $vatRate = 0.25; // Default 25%
                                        $totalTax = (int) round($totalAmount * ($vatRate / (1 + $vatRate)));
                                    }
                                    
                                    if ($totalTax > 0) {
                                        $subtotal = isset($metadata['subtotal']) && $metadata['subtotal'] > 0
                                            ? (int) $metadata['subtotal'] 
                                            : $record->amount;
                                        $subtotalExcludingTax = $subtotal - $totalTax;
                                        $vatRate = $subtotalExcludingTax > 0 
                                            ? ($totalTax / $subtotalExcludingTax) * 100 
                                            : 25.0;
                                        return sprintf(
                                            'Total VAT: %.2f NOK (%.2f%%)',
                                            $totalTax / 100,
                                            $vatRate
                                        );
                                    }
                                    return 'No VAT';
                                }
                                
                                // Group items by VAT rate
                                $vatGroups = [];
                                foreach ($items as $item) {
                                    if (!is_array($item)) {
                                        continue;
                                    }
                                    
                                    // All prices in øre (cents)
                                    $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
                                    $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                                    $discountAmount = isset($item['discount_amount']) ? (int) $item['discount_amount'] : 0;
                                    
                                    // Line total in øre
                                    $lineTotal = ($unitPrice * $quantity) - ($discountAmount * $quantity);
                                    
                                    if ($lineTotal <= 0) {
                                        continue;
                                    }
                                    
                                    // Get VAT percent from item (as percentage, e.g., 25.0 for 25%)
                                    // Try multiple sources: vat_percent, article_group_code mapping, product lookup, or default
                                    $vatPercent = null;
                                    
                                    if (isset($item['vat_percent'])) {
                                        $vatPercent = (float) $item['vat_percent'];
                                    } elseif (isset($item['article_group_code'])) {
                                        // Try to get VAT from article group code
                                        $vatPercent = \App\Services\SafTCodeMapper::getVatPercentFromArticleGroupCode($item['article_group_code']);
                                    } elseif (isset($item['product_id'])) {
                                        // Fetch product from database to get vat_percent or article_group_code
                                        try {
                                            $product = \App\Models\ConnectedProduct::find($item['product_id']);
                                            if ($product) {
                                                if ($product->vat_percent) {
                                                    $vatPercent = (float) $product->vat_percent;
                                                } elseif ($product->article_group_code) {
                                                    $vatPercent = \App\Services\SafTCodeMapper::getVatPercentFromArticleGroupCode($product->article_group_code);
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            // Product might be deleted, continue with default
                                        }
                                    }
                                    
                                    // If still not available, try to calculate from item tax
                                    if ($vatPercent === null) {
                                        $itemTax = isset($item['tax_amount']) 
                                            ? (int) $item['tax_amount'] 
                                            : 0;
                                        
                                        if ($itemTax > 0) {
                                            // Calculate VAT rate: tax / (total - tax) * 100
                                            $baseAmount = $lineTotal - $itemTax;
                                            $vatPercent = $baseAmount > 0 
                                                ? ($itemTax / $baseAmount) * 100 
                                                : 25.0;
                                        } else {
                                            // Default to 25% if no tax info
                                            $vatPercent = 25.0;
                                        }
                                    }
                                    
                                    $vatKey = number_format($vatPercent, 2) . '%';
                                    if (!isset($vatGroups[$vatKey])) {
                                        $vatGroups[$vatKey] = [
                                            'rate' => $vatPercent,
                                            'subtotal' => 0, // in øre
                                            'tax' => 0,      // in øre
                                        ];
                                    }
                                    
                                    // Calculate tax for this item (tax-inclusive pricing)
                                    // Tax = Total × (Rate / (100 + Rate))
                                    $itemTax = (int) round($lineTotal * ($vatPercent / (100 + $vatPercent)));
                                    $itemBase = $lineTotal - $itemTax;
                                    
                                    $vatGroups[$vatKey]['subtotal'] += $itemBase;
                                    $vatGroups[$vatKey]['tax'] += $itemTax;
                                }
                                
                                if (empty($vatGroups)) {
                                    // Fallback: calculate total VAT from amount (assuming 25% default)
                                    $totalAmount = $record->amount;
                                    $vatRate = 0.25; // Default 25%
                                    $totalTax = (int) round($totalAmount * ($vatRate / (1 + $vatRate)));
                                    $subtotalExcludingTax = $totalAmount - $totalTax;
                                    
                                    return sprintf(
                                        'Total VAT: %.2f NOK (%.2f%%, Base: %.2f NOK)',
                                        $totalTax / 100,
                                        $vatRate * 100,
                                        $subtotalExcludingTax / 100
                                    );
                                }
                                
                                $lines = [];
                                $totalTaxCalculated = 0;
                                $totalBaseCalculated = 0;
                                
                                foreach ($vatGroups as $rate => $group) {
                                    $totalTaxCalculated += $group['tax'];
                                    $totalBaseCalculated += $group['subtotal'];
                                    $lines[] = sprintf(
                                        '%s: %.2f NOK (Base: %.2f NOK)',
                                        $rate,
                                        $group['tax'] / 100,
                                        $group['subtotal'] / 100
                                    );
                                }
                                
                                // Add total line
                                if (count($vatGroups) > 1) {
                                    $lines[] = sprintf(
                                        'Total: %.2f NOK (Base: %.2f NOK)',
                                        $totalTaxCalculated / 100,
                                        $totalBaseCalculated / 100
                                    );
                                }
                                
                                return implode("\n", $lines);
                            })
                            ->listWithLineBreaks()
                            ->size(TextSize::Medium)
                            ->icon(Heroicon::OutlinedReceiptPercent)
                            ->columnSpanFull(),

                        TextEntry::make('total_tax_display')
                            ->label('Total VAT')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                
                                // Try to get from metadata first
                                $tax = isset($metadata['total_tax']) && $metadata['total_tax'] > 0
                                    ? (int) $metadata['total_tax'] 
                                    : null;
                                
                                // If not available or 0, calculate from items
                                if ($tax === null || $tax <= 0) {
                                    $items = $metadata['items'] ?? [];
                                    $calculatedTax = 0;
                                    
                                    if (!empty($items) && is_array($items)) {
                                        foreach ($items as $item) {
                                            if (!is_array($item)) {
                                                continue;
                                            }
                                            
                                            $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
                                            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                                            $discountAmount = isset($item['discount_amount']) ? (int) $item['discount_amount'] : 0;
                                            
                                            $lineTotal = ($unitPrice * $quantity) - ($discountAmount * $quantity);
                                            
                                            if ($lineTotal <= 0) {
                                                continue;
                                            }
                                            
                                            // Get VAT percent from item - try multiple sources
                                            $vatPercent = null;
                                            
                                            if (isset($item['vat_percent'])) {
                                                $vatPercent = (float) $item['vat_percent'];
                                            } elseif (isset($item['article_group_code'])) {
                                                $vatPercent = \App\Services\SafTCodeMapper::getVatPercentFromArticleGroupCode($item['article_group_code']);
                                            } elseif (isset($item['product_id'])) {
                                                try {
                                                    $product = \App\Models\ConnectedProduct::find($item['product_id']);
                                                    if ($product) {
                                                        if ($product->vat_percent) {
                                                            $vatPercent = (float) $product->vat_percent;
                                                        } elseif ($product->article_group_code) {
                                                            $vatPercent = \App\Services\SafTCodeMapper::getVatPercentFromArticleGroupCode($product->article_group_code);
                                                        }
                                                    }
                                                } catch (\Exception $e) {
                                                    // Product might be deleted, continue with default
                                                }
                                            }
                                            
                                            // Default to 25% if no VAT info found
                                            if ($vatPercent === null) {
                                                $vatPercent = 25.0;
                                            }
                                            
                                            // Tax-inclusive: Tax = Total × (Rate / (100 + Rate))
                                            $itemTax = (int) round($lineTotal * ($vatPercent / (100 + $vatPercent)));
                                            $calculatedTax += $itemTax;
                                        }
                                    }
                                    
                                    // If still no tax calculated, calculate from total amount (fallback)
                                    if ($calculatedTax <= 0) {
                                        $totalAmount = $record->amount;
                                        $vatRate = 0.25; // Default 25%
                                        $calculatedTax = (int) round($totalAmount * ($vatRate / (1 + $vatRate)));
                                    }
                                    
                                    $tax = $calculatedTax;
                                }
                                
                                if ($tax <= 0) {
                                    return '0.00 NOK';
                                }
                                
                                return number_format($tax / 100, 2) . ' NOK';
                            })
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedReceiptPercent),

                        TextEntry::make('tip_display')
                            ->label('Tip')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $tip = ($metadata['tip_amount'] ?? $record->tip_amount ?? 0) / 100;
                                if ($tip <= 0) {
                                    return null;
                                }
                                return number_format($tip, 2) . ' NOK';
                            })
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedHeart)
                            ->visible(fn ($record) => (($record->metadata['tip_amount'] ?? $record->tip_amount ?? 0) > 0)),

                        TextEntry::make('formatted_amount')
                            ->label('Total Paid')
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success')
                            ->weight('bold')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->icon(Heroicon::OutlinedCalculator),

                // Payment Status
                Section::make('Payment Status')
                    ->schema([
                        IconEntry::make('paid')
                            ->label('Payment Received')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger')
                            ->size(IconSize::Large),

                        IconEntry::make('captured')
                            ->label('Payment Captured')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('warning')
                            ->size(IconSize::Large),

                        IconEntry::make('refunded')
                            ->label('Refunded')
                            ->boolean()
                            ->trueIcon('heroicon-o-x-circle')
                            ->falseIcon('heroicon-o-check-circle')
                            ->trueColor('danger')
                            ->falseColor('gray')
                            ->size(IconSize::Large),

                        TextEntry::make('formatted_amount_refunded')
                            ->label('Refund Amount')
                            ->badge()
                            ->color('warning')
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->size(TextSize::Large)
                            ->visible(fn ($record) => $record->amount_refunded > 0),
                    ])
                    ->columns(4)
                    ->icon(Heroicon::OutlinedCreditCard),

                // SAF-T Compliance (collapsed by default)
                Section::make('SAF-T Compliance')
                    ->schema([
                        TextEntry::make('payment_code')
                            ->label('Payment Code (SAF-T)')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-')
                            ->copyable(),

                        TextEntry::make('transaction_code')
                            ->label('Transaction Code (SAF-T)')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-')
                            ->copyable(),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->icon(Heroicon::OutlinedDocumentCheck),

                // Technical Details (collapsed by default)
                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_charge_id')
                            ->label('Stripe Charge ID')
                            ->copyable()
                            ->placeholder('Cash payment (no Stripe ID)')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->visible(fn ($record) => $record->stripe_charge_id),

                        TextEntry::make('stripe_payment_intent_id')
                            ->label('Payment Intent ID')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->visible(fn ($record) => $record->stripe_payment_intent_id),

                        TextEntry::make('failure_code')
                            ->label('Failure Code')
                            ->placeholder('-')
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record) => $record->failure_code),

                        TextEntry::make('failure_message')
                            ->label('Failure Message')
                            ->placeholder('-')
                            ->wrap()
                            ->color('danger')
                            ->visible(fn ($record) => $record->failure_message),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('d.m.Y H:i:s')
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('d.m.Y H:i:s')
                            ->icon(Heroicon::OutlinedCalendar),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->icon(Heroicon::OutlinedCog6Tooth),
            ]);
    }
}
