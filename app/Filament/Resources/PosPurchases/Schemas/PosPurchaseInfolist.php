<?php

namespace App\Filament\Resources\PosPurchases\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class PosPurchaseInfolist
{
    /**
     * Get tax rate (0-1) for an article group code. Uses ArticleGroupCode model, defaults to 25%.
     */
    public static function getTaxRateForArticleGroupCode(?string $code): float
    {
        if (! $code) {
            return 0.25;
        }
        $agc = \App\Models\ArticleGroupCode::where('code', $code)->where('active', true)->first();
        if ($agc && $agc->default_vat_percent !== null) {
            return (float) $agc->default_vat_percent;
        }

        return 0.25;
    }

    /**
     * Build VAT breakdown per rate from purchase metadata items (for summary and receipt).
     * Returns array of [ 'rate' => '25%', 'amount' => '20.00 NOK' ] for RepeatableEntry.
     */
    public static function buildTaxBreakdownForRecord($record): array
    {
        $metadata = $record->metadata ?? [];
        $items = $metadata['items'] ?? [];
        $byRate = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $unitPriceOre = (int) ($item['unit_price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 1);
            $discountOre = (int) ($item['discount_amount'] ?? 0);
            $lineSubtotalOre = (int) round($unitPriceOre * $quantity - $discountOre * $quantity);
            if ($lineSubtotalOre <= 0) {
                continue;
            }
            $rate = null;
            if (isset($item['tax_rate']) && $item['tax_rate'] !== null && $item['tax_rate'] !== '') {
                $rate = (float) $item['tax_rate'];
            }
            if ($rate === null) {
                $code = $item['article_group_code'] ?? null;
                $rate = self::getTaxRateForArticleGroupCode($code);
            }
            $ratePercent = (int) round($rate * 100);
            $lineTaxOre = $rate > 0 ? (int) round($lineSubtotalOre * ($rate / (1 + $rate))) : 0;
            if (! isset($byRate[$ratePercent])) {
                $byRate[$ratePercent] = 0;
            }
            $byRate[$ratePercent] += $lineTaxOre;
        }

        if (empty($byRate)) {
            $totalTaxOre = isset($metadata['total_tax']) ? (int) $metadata['total_tax'] : null;
            if ($totalTaxOre !== null && $totalTaxOre >= 0) {
                return [['rate' => '—', 'amount' => number_format($totalTaxOre / 100, 2).' NOK']];
            }

            return [];
        }

        krsort($byRate, SORT_NUMERIC);
        $breakdown = [];
        foreach ($byRate as $ratePercent => $taxOre) {
            $breakdown[] = [
                'rate' => $ratePercent.'%',
                'amount' => number_format($taxOre / 100, 2).' NOK',
            ];
        }

        return $breakdown;
    }

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
                            ->color(fn ($state) => match ($state) {
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

                                return 'Cash Payment #'.$record->id;
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
                            ->visible(fn ($record) => ! $record->customer && ($record->metadata['customer_name'] ?? null)),

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
                    ->visible(fn ($record) => ! empty($record->metadata['note'] ?? null)),

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

                // Cart Items - Display using RepeatableEntry for better layout
                Section::make('Items Purchased')
                    ->schema([
                        RepeatableEntry::make('items')
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

                                            // Calculate derived values for display
                                            $quantity = $cleanedItem['quantity'] ?? 1;
                                            $unitPrice = ($cleanedItem['unit_price'] ?? 0) / 100;
                                            $discountAmount = ($cleanedItem['discount_amount'] ?? 0) / 100;
                                            $originalPrice = isset($cleanedItem['original_price']) ? ($cleanedItem['original_price'] / 100) : null;
                                            $lineTotal = ($unitPrice * $quantity) - ($discountAmount * $quantity);

                                            // Get product name from snapshot or fetch from database
                                            $productName = $cleanedItem['product_name'] ?? null;
                                            if (! $productName) {
                                                $productId = $cleanedItem['product_id'] ?? null;
                                                $variantId = $cleanedItem['variant_id'] ?? null;

                                                if ($variantId) {
                                                    $variant = \App\Models\ProductVariant::find($variantId);
                                                    if ($variant && $variant->product) {
                                                        $productName = $variant->product->name;
                                                        if ($variant->variant_name !== 'Default') {
                                                            $productName .= ' - '.$variant->variant_name;
                                                        }
                                                    } elseif ($productId) {
                                                        $product = \App\Models\ConnectedProduct::find($productId);
                                                        $productName = $product ? $product->name : "Product #{$productId}";
                                                    }
                                                } elseif ($productId) {
                                                    $product = \App\Models\ConnectedProduct::find($productId);
                                                    $productName = $product ? $product->name : "Product #{$productId}";
                                                }

                                                if (! $productName) {
                                                    $productName = 'Unknown Product';
                                                }
                                            }

                                            // Tax: use cart item tax_rate when present (from API), else resolve from article_group_code
                                            $taxRate = null;
                                            if (isset($cleanedItem['tax_rate']) && $cleanedItem['tax_rate'] !== null && $cleanedItem['tax_rate'] !== '') {
                                                $taxRate = (float) $cleanedItem['tax_rate'];
                                            }
                                            if ($taxRate === null) {
                                                $articleGroupCode = $cleanedItem['article_group_code'] ?? null;
                                                $taxRate = self::getTaxRateForArticleGroupCode($articleGroupCode);
                                            }
                                            $unitPriceOre = (int) ($cleanedItem['unit_price'] ?? 0);
                                            $discountOre = (int) ($cleanedItem['discount_amount'] ?? 0);
                                            $itemSubtotalInclTaxOre = (int) round($unitPriceOre * $quantity - $discountOre * $quantity);
                                            $itemTaxOre = $taxRate > 0
                                                ? (int) round($itemSubtotalInclTaxOre * ($taxRate / (1 + $taxRate)))
                                                : 0;
                                            $cleanedItem['display_tax_amount'] = $itemTaxOre / 100;

                                            // Add calculated values to item
                                            $cleanedItem['display_name'] = $productName;
                                            $cleanedItem['display_quantity'] = $quantity;
                                            $cleanedItem['display_unit_price'] = $unitPrice;
                                            $cleanedItem['display_original_price'] = $originalPrice;
                                            $cleanedItem['display_discount_amount'] = $discountAmount;
                                            $cleanedItem['display_line_total'] = $lineTotal;

                                            $items[] = $cleanedItem;
                                        }
                                    }
                                }

                                return $items;
                            })
                            ->table([
                                TableColumn::make('Product'),
                                TableColumn::make('Qty')
                                    ->alignment(Alignment::Center)
                                    ->width('60px'),
                                TableColumn::make('Unit Price')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Discount')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Tax')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Total')
                                    ->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('display_name')
                                    ->label('Product')
                                    ->weight('bold'),

                                TextEntry::make('display_quantity')
                                    ->label('Qty')
                                    ->formatStateUsing(fn ($state) => $state ?? 1)
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('display_unit_price')
                                    ->label('Unit Price')
                                    ->formatStateUsing(function ($state, $get) {
                                        $originalPrice = $get('display_original_price');

                                        if ($originalPrice && $originalPrice > $state) {
                                            return '<span style="text-decoration: line-through; color: #9ca3af;">'.number_format($originalPrice, 2).'</span> '.number_format($state, 2).' NOK';
                                        }

                                        return number_format($state, 2).' NOK';
                                    })
                                    ->html(),

                                TextEntry::make('display_discount_amount')
                                    ->label('Discount')
                                    ->formatStateUsing(fn ($state) => $state > 0 ? '-'.number_format($state, 2).' NOK' : '-')
                                    ->badge()
                                    ->color(fn ($get) => ($get('display_discount_amount') ?? 0) > 0 ? 'success' : 'gray'),

                                TextEntry::make('display_tax_amount')
                                    ->label('Tax')
                                    ->formatStateUsing(fn ($state) => $state !== null && $state > 0 ? number_format($state, 2).' NOK' : '-')
                                    ->badge()
                                    ->color('gray'),

                                TextEntry::make('display_line_total')
                                    ->label('Total')
                                    ->formatStateUsing(fn ($state) => number_format($state, 2).' NOK')
                                    ->weight('bold')
                                    ->badge()
                                    ->color('primary'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->icon(Heroicon::OutlinedShoppingBag),

                // Purchase Summary
                Section::make('Purchase Summary')
                    ->schema([
                        TextEntry::make('subtotal_display')
                            ->label('Subtotal')
                            ->state(function ($record) {
                                // Use same calculation logic as API (PurchasesController)
                                $metadata = $record->metadata ?? [];
                                $items = $metadata['items'] ?? [];

                                // Calculate subtotal from items (same as API)
                                $calculatedSubtotal = 0;
                                if (! empty($items) && is_array($items)) {
                                    foreach ($items as $item) {
                                        if (! is_array($item)) {
                                            continue;
                                        }
                                        $unitPrice = isset($item['unit_price']) ? (int) $item['unit_price'] : 0;
                                        $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 1.0;
                                        // Calculate line totals with decimal quantities, then round to integer (øre)
                                        $lineSubtotal = (int) round($unitPrice * $quantity);
                                        $calculatedSubtotal += $lineSubtotal;
                                    }
                                }

                                // Get metadata values
                                $metadataSubtotal = isset($metadata['subtotal']) ? (int) $metadata['subtotal'] : null;

                                // Use calculated subtotal if metadata is missing or 0 (same as API)
                                $subtotal = ($metadataSubtotal === null || ($metadataSubtotal === 0 && $calculatedSubtotal > 0))
                                    ? $calculatedSubtotal
                                    : ($metadataSubtotal ?? $record->amount);

                                return number_format($subtotal / 100, 2).' NOK';
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

                                return '-'.number_format($discounts, 2).' NOK';
                            })
                            ->badge()
                            ->color('success')
                            ->icon(Heroicon::OutlinedTag)
                            ->visible(fn ($record) => ($record->metadata['total_discounts'] ?? 0) > 0),

                        RepeatableEntry::make('tax_breakdown')
                            ->label('Tax')
                            ->state(fn ($record) => self::buildTaxBreakdownForRecord($record))
                            ->table([
                                TableColumn::make('Rate')->alignment(Alignment::End),
                                TableColumn::make('Amount')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('rate')
                                    ->label('Rate')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('amount')
                                    ->label('Amount'),
                            ])
                            ->columnSpanFull(),

                        TextEntry::make('tip_display')
                            ->label('Tip')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $tip = ($metadata['tip_amount'] ?? $record->tip_amount ?? 0) / 100;
                                if ($tip <= 0) {
                                    return null;
                                }

                                return number_format($tip, 2).' NOK';
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
