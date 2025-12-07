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

                // Purchase Summary
                Section::make('Purchase Summary')
                    ->schema([
                        TextEntry::make('subtotal_display')
                            ->label('Subtotal')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $subtotal = ($metadata['subtotal'] ?? $record->amount) / 100;
                                return number_format($subtotal, 2) . ' NOK';
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

                        TextEntry::make('tax_display')
                            ->label('Tax')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $tax = ($metadata['total_tax'] ?? 0) / 100;
                                if ($tax <= 0) {
                                    return '0.00 NOK';
                                }
                                return number_format($tax, 2) . ' NOK';
                            })
                            ->size(TextSize::Large)
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
