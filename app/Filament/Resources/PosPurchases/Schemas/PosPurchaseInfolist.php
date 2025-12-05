<?php

namespace App\Filament\Resources\PosPurchases\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class PosPurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase Information')
                    ->schema([
                        TextEntry::make('formatted_amount')
                            ->label('Amount')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->colors([
                                'success' => 'succeeded',
                                'warning' => 'pending',
                                'danger' => ['failed', 'refunded'],
                                'info' => 'processing',
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle),

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
                            ->icon(Heroicon::OutlinedCreditCard),

                        TextEntry::make('charge_display')
                            ->label('Charge ID')
                            ->formatStateUsing(function ($record) {
                                if ($record->stripe_charge_id) {
                                    return $record->stripe_charge_id;
                                }
                                return 'Cash Payment #' . $record->id;
                            })
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),
                    ])
                    ->columns(2),

                Section::make('POS Session & Receipt')
                    ->schema([
                        TextEntry::make('posSession.session_number')
                            ->label('POS Session')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->url(fn ($record) => $record->posSession
                                ? \App\Filament\Resources\PosSessions\PosSessionResource::getUrl('view', ['record' => $record->posSession])
                                : null),

                        TextEntry::make('posSession.status')
                            ->label('Session Status')
                            ->badge()
                            ->colors([
                                'success' => 'open',
                                'gray' => 'closed',
                            ])
                            ->icon(Heroicon::OutlinedCircleStack)
                            ->visible(fn ($record) => $record->posSession),

                        TextEntry::make('receipt.receipt_number')
                            ->label('Receipt')
                            ->badge()
                            ->color('gray')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->url(fn ($record) => $record->receipt
                                ? \App\Filament\Resources\Receipts\ReceiptResource::getUrl('preview', ['record' => $record->receipt])
                                : null)
                            ->placeholder('No receipt generated'),

                        TextEntry::make('posSession.user.name')
                            ->label('Cashier')
                            ->icon(Heroicon::OutlinedUser)
                            ->visible(fn ($record) => $record->posSession && $record->posSession->user),
                    ])
                    ->columns(2),

                Section::make('Payment Details')
                    ->schema([
                        IconEntry::make('paid')
                            ->label('Paid')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        IconEntry::make('captured')
                            ->label('Captured')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('warning'),

                        IconEntry::make('refunded')
                            ->label('Refunded')
                            ->boolean()
                            ->trueIcon('heroicon-o-x-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('danger')
                            ->falseColor('gray'),

                        TextEntry::make('formatted_amount_refunded')
                            ->label('Amount Refunded')
                            ->placeholder('$0.00')
                            ->badge()
                            ->color(fn ($record) => $record->amount_refunded > 0 ? 'warning' : 'gray')
                            ->visible(fn ($record) => $record->amount_refunded > 0),

                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color(fn ($record) => $record->paid_at && $record->paid_at->isPast() ? 'success' : null),
                    ])
                    ->columns(3),

                Section::make('SAF-T Compliance')
                    ->schema([
                        TextEntry::make('payment_code')
                            ->label('Payment Code (SAF-T)')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-'),

                        TextEntry::make('transaction_code')
                            ->label('Transaction Code (SAF-T)')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Cart Items')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Items')
                            ->formatStateUsing(function ($state, $record) {
                                $metadata = $record->metadata ?? [];
                                $items = $metadata['items'] ?? [];

                                if (!is_array($items) || empty($items)) {
                                    return 'No items';
                                }

                                // Pre-load all products in one query for efficiency
                                $productIds = collect($items)->pluck('product_id')->filter()->unique()->toArray();
                                $products = [];
                                if (!empty($productIds)) {
                                    $products = \App\Models\ConnectedProduct::whereIn('id', $productIds)
                                        ->get()
                                        ->keyBy('id');
                                }

                                $formattedItems = collect($items)->map(function ($item) use ($products) {
                                    $quantity = $item['quantity'] ?? 1;
                                    $unitPrice = ($item['unit_price'] ?? 0) / 100;
                                    $total = $unitPrice * $quantity;
                                    $productId = $item['product_id'] ?? null;
                                    $variantId = $item['variant_id'] ?? null;
                                    $discountAmount = ($item['discount_amount'] ?? 0) / 100;
                                    $productCode = $item['product_code'] ?? null;
                                    $articleGroupCode = $item['article_group_code'] ?? null;

                                    // Get product name
                                    $productName = 'Unknown Product';
                                    if ($productId && isset($products[$productId])) {
                                        $product = $products[$productId];
                                        $productName = $product->name ?? "Product #{$productId}";
                                    } elseif ($productId) {
                                        $productName = "Product #{$productId}";
                                    }

                                    // Build item description
                                    $description = "{$quantity}x {$productName}";
                                    
                                    // Add variant info if available
                                    if ($variantId) {
                                        $description .= " (Variant #{$variantId})";
                                    }
                                    
                                    // Add product code if available
                                    if ($productCode) {
                                        $description .= " [Code: {$productCode}]";
                                    }
                                    
                                    // Add article group code if available
                                    if ($articleGroupCode) {
                                        $description .= " [Group: {$articleGroupCode}]";
                                    }
                                    
                                    // Add price info
                                    $description .= "\n  Unit: " . number_format($unitPrice, 2) . ' NOK';
                                    
                                    // Add discount if applicable
                                    if ($discountAmount > 0) {
                                        $description .= " (Discount: " . number_format($discountAmount, 2) . ' NOK)';
                                    }
                                    
                                    // Add total
                                    $description .= " → Total: " . number_format($total, 2) . ' NOK';

                                    return $description;
                                })->join("\n\n");

                                return $formattedItems ?: 'No items';
                            })
                            ->listWithLineBreaks()
                            ->placeholder('No items')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

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
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
