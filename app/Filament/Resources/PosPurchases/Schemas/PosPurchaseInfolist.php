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

                // Customer information (if available)
                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Customer Name')
                            ->icon(Heroicon::OutlinedUser)
                            ->badge()
                            ->color('info')
                            ->url(fn ($record) => $record->customer
                                ? \App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource::getUrl('view', ['record' => $record->customer])
                                : null)
                            ->visible(fn ($record) => $record->customer),

                        TextEntry::make('metadata.customer_name')
                            ->label('Customer Name')
                            ->icon(Heroicon::OutlinedUser)
                            ->badge()
                            ->color('info')
                            ->visible(fn ($record) => !$record->customer && ($record->metadata['customer_name'] ?? null)),

                        TextEntry::make('customer.email')
                            ->label('Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable()
                            ->visible(fn ($record) => $record->customer && $record->customer->email),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
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

                // Cart Items - Receipt-like display
                Section::make('Items Purchased')
                    ->schema([
                        TextEntry::make('items_display')
                            ->label('')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $items = $metadata['items'] ?? [];

                                if (!is_array($items) || empty($items)) {
                                    return '<div class="text-gray-500 italic">No items in this purchase</div>';
                                }

                                // Pre-load all products in one query for efficiency
                                $productIds = collect($items)->pluck('product_id')->filter()->unique()->toArray();
                                $products = [];
                                if (!empty($productIds)) {
                                    $products = \App\Models\ConnectedProduct::whereIn('id', $productIds)
                                        ->get()
                                        ->keyBy('id');
                                }

                                $html = '<div class="space-y-3">';
                                
                                foreach ($items as $item) {
                                    $quantity = $item['quantity'] ?? 1;
                                    $unitPrice = ($item['unit_price'] ?? 0) / 100;
                                    $total = $unitPrice * $quantity;
                                    $productId = $item['product_id'] ?? null;
                                    $variantId = $item['variant_id'] ?? null;
                                    $discountAmount = ($item['discount_amount'] ?? 0) / 100;
                                    $productCode = $item['product_code'] ?? null;

                                    // Get product name
                                    $productName = 'Unknown Product';
                                    if ($productId && isset($products[$productId])) {
                                        $product = $products[$productId];
                                        $productName = $product->name ?? "Product #{$productId}";
                                    } elseif ($productId) {
                                        $productName = "Product #{$productId}";
                                    }

                                    $html .= '<div class="border-b border-gray-200 pb-2">';
                                    $html .= '<div class="flex justify-between items-start mb-1">';
                                    $html .= '<div class="flex-1">';
                                    $html .= '<div class="font-semibold text-gray-900">' . htmlspecialchars($productName) . '</div>';
                                    
                                    if ($productCode) {
                                        $html .= '<div class="text-xs text-gray-500">Code: ' . htmlspecialchars($productCode) . '</div>';
                                    }
                                    
                                    if ($variantId) {
                                        $html .= '<div class="text-xs text-gray-500">Variant #' . htmlspecialchars($variantId) . '</div>';
                                    }
                                    
                                    $html .= '</div>';
                                    $html .= '<div class="text-right ml-4">';
                                    $html .= '<div class="font-semibold text-gray-900">' . number_format($total, 2) . ' NOK</div>';
                                    $html .= '<div class="text-sm text-gray-500">' . $quantity . ' × ' . number_format($unitPrice, 2) . ' NOK</div>';
                                    
                                    if ($discountAmount > 0) {
                                        $html .= '<div class="text-xs text-green-600">- ' . number_format($discountAmount, 2) . ' NOK discount</div>';
                                    }
                                    
                                    $html .= '</div>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';
                                return $html;
                            })
                            ->html()
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
