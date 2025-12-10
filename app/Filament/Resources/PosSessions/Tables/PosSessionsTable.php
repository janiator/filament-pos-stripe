<?php

namespace App\Filament\Resources\PosSessions\Tables;

use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\PosSession;
use App\Models\PosEvent;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PosSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_number')
                    ->label('Session #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),
                TextColumn::make('posDevice.device_name')
                    ->label('Device')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable(),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Closed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('transaction_count')
                    ->label('Transactions')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('nok', divideBy: 100)
                    ->sortable(),
                TextColumn::make('cash_difference')
                    ->label('Cash Diff')
                    ->money('nok', divideBy: 100)
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordUrl(fn ($record) => PosSessionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (PosSession $record): bool => $record->status !== 'closed'),
                Action::make('x_report')
                    ->label('X-Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('info')
                    ->modalHeading('X-Report (Interim Report)')
                    ->before(function (PosSession $record) {
                        // Generate report data first
                        $report = self::generateXReport($record);
                        
                        // Log X-report event (13008) per § 2-8-2
                        // Include complete report data in event_data for electronic journal compliance
                        PosEvent::create([
                            'store_id' => $record->store_id,
                            'pos_device_id' => $record->pos_device_id,
                            'pos_session_id' => $record->id,
                            'user_id' => auth()->id(),
                            'event_code' => PosEvent::EVENT_X_REPORT,
                            'event_type' => 'report',
                            'description' => "X-report for session {$record->session_number}",
                            'event_data' => [
                                'report_type' => 'X-Report',
                                'session_number' => $record->session_number,
                                'report_data' => $report, // Complete report data for electronic journal
                            ],
                            'occurred_at' => now(),
                        ]);
                    })
                    ->modalContent(fn (PosSession $record) => view('filament.resources.pos-reports.modals.x-report', [
                        'session' => $record,
                        'report' => self::generateXReport($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (PosSession $record): bool => $record->status === 'open'),
                Action::make('z_report')
                    ->label('Z-Report')
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->modalHeading('Z-Report (End-of-Day Report)')
                    ->before(function (PosSession $record) {
                        // Generate report data first
                        $report = self::generateZReport($record);
                        
                        // Log Z-report event (13009) per § 2-8-3
                        // Include complete report data in event_data for electronic journal compliance
                        PosEvent::create([
                            'store_id' => $record->store_id,
                            'pos_device_id' => $record->pos_device_id,
                            'pos_session_id' => $record->id,
                            'user_id' => auth()->id(),
                            'event_code' => PosEvent::EVENT_Z_REPORT,
                            'event_type' => 'report',
                            'description' => "Z-report for session {$record->session_number}",
                            'event_data' => [
                                'report_type' => 'Z-Report',
                                'session_number' => $record->session_number,
                                'report_data' => $report, // Complete report data for electronic journal
                            ],
                            'occurred_at' => now(),
                        ]);
                    })
                    ->modalContent(fn (PosSession $record) => view('filament.resources.pos-reports.modals.z-report', [
                        'session' => $record,
                        'report' => self::generateZReport($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (PosSession $record): bool => $record->status === 'closed'),
                Action::make('close')
                    ->label('Close Session')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Close POS Session')
                    ->modalDescription(fn (PosSession $record): string => "Are you sure you want to close session {$record->session_number}? This will calculate expected cash and mark the session as closed.")
                    ->form([
                        \Filament\Forms\Components\TextInput::make('actual_cash')
                            ->label('Actual Cash')
                            ->numeric()
                            ->suffix('kr')
                            ->step(0.01)
                            ->helperText('Enter the actual cash count at closing in NOK. Leave empty to use expected cash.'),
                        \Filament\Forms\Components\Textarea::make('closing_notes')
                            ->label('Closing Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Optional notes about the session closing.'),
                    ])
                    ->visible(fn (PosSession $record): bool => $record->status === 'open')
                    ->action(function (PosSession $record, array $data): void {
                        if (!$record->canBeClosed()) {
                            Notification::make()
                                ->title('Cannot close session')
                                ->danger()
                                ->body('This session cannot be closed.')
                                ->send();
                            return;
                        }

                        // Convert from NOK to øre (multiply by 100)
                        $actualCash = isset($data['actual_cash']) && $data['actual_cash'] !== '' && $data['actual_cash'] !== null
                            ? (int) round((float) $data['actual_cash'] * 100)
                            : null;

                        $success = $record->close($actualCash, $data['closing_notes'] ?? null);

                        if ($success) {
                            Notification::make()
                                ->title('Session closed')
                                ->success()
                                ->body("Session {$record->session_number} has been closed successfully.")
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to close session')
                                ->danger()
                                ->body('An error occurred while closing the session.')
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // No bulk actions for sessions
                ]),
            ]);
    }

    /**
     * Generate X-report data for a session
     */
    public static function generateXReport(PosSession $session): array
    {
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $charges = $session->charges->where('status', 'succeeded');
        
        // Get settings to check if tips are enabled
        $settings = \App\Models\Setting::getForStore($session->store_id);
        $tipsEnabled = (bool) ($settings->tips_enabled ?? true);
        
        $totalAmount = $charges->sum('amount');
        $cashAmount = $charges->where('payment_method', 'cash')->sum('amount');
        $cardAmount = $charges->where('payment_method', 'card')->sum('amount');
        $mobileAmount = $charges->where('payment_method', 'mobile')->sum('amount');
        $otherAmount = $totalAmount - $cashAmount - $cardAmount - $mobileAmount;
        $totalTips = $tipsEnabled ? $charges->sum('tip_amount') : 0;
        
        // Calculate VAT (25% standard in Norway)
        $vatRate = 0.25;
        $vatBase = round($totalAmount / (1 + $vatRate), 0);
        $vatAmount = $totalAmount - $vatBase;
        
        // Payment method breakdown
        $byPaymentMethod = $charges->groupBy('payment_method')->map(function ($group) use ($tipsEnabled) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
                'tips' => $tipsEnabled ? $group->sum('tip_amount') : 0,
            ];
        });
        
        // Transaction breakdown by code
        $transactionsByType = $charges->groupBy('transaction_code')->map(function ($group) {
            return [
                'code' => $group->first()->transaction_code,
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        });
        
        // Payment code breakdown
        $byPaymentCode = $charges->groupBy('payment_code')->map(function ($group) {
            return [
                'code' => $group->first()->payment_code,
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        });
        
        // Cash drawer events
        $cashDrawerOpens = $session->events->where('event_code', PosEvent::EVENT_CASH_DRAWER_OPEN)->count();
        $nullinnslagCount = $session->events
            ->where('event_code', PosEvent::EVENT_CASH_DRAWER_OPEN)
            ->filter(function ($event) {
                $eventData = $event->event_data ?? [];
                return isset($eventData['nullinnslag']) && $eventData['nullinnslag'] === true;
            })
            ->count();
        
        // Receipt count
        $receiptCount = $session->receipts->count();
        
        // Calculate manual discounts (only discounts applied manually at cash point)
        // Manual discounts are those with discountReason set (not automatic/campaign discounts)
        $manualDiscounts = self::calculateManualDiscounts($session);
        
        // Line corrections (only reductions count)
        $lineCorrections = self::calculateLineCorrections($session);
        
        return [
            'session_id' => $session->id,
            'session_number' => $session->session_number,
            'opened_at' => $session->opened_at,
            'report_generated_at' => now(),
            'store' => [
                'id' => $session->store->id,
                'name' => $session->store->name,
            ],
            'device' => $session->posDevice ? [
                'id' => $session->posDevice->id,
                'name' => $session->posDevice->device_name,
            ] : null,
            'cashier' => $session->user ? [
                'id' => $session->user->id,
                'name' => $session->user->name,
            ] : null,
            'opening_balance' => $session->opening_balance,
            'transactions_count' => $charges->count(),
            'total_amount' => $totalAmount,
            'vat_base' => $vatBase,
            'vat_amount' => $vatAmount,
            'vat_rate' => $vatRate * 100,
            'cash_amount' => $cashAmount,
            'card_amount' => $cardAmount,
            'mobile_amount' => $mobileAmount,
            'other_amount' => $otherAmount,
            'total_tips' => $totalTips,
            'expected_cash' => $session->calculateExpectedCash(),
            'by_payment_method' => $byPaymentMethod,
            'by_payment_code' => $byPaymentCode,
            'transactions_by_type' => $transactionsByType,
            'cash_drawer_opens' => $cashDrawerOpens,
            'nullinnslag_count' => $nullinnslagCount,
            'receipt_count' => $receiptCount,
            'charges' => $charges,
            'tips_enabled' => $tipsEnabled,
            'sales_by_category' => self::calculateSalesByCategory($session),
            'manual_discounts' => $manualDiscounts,
            'line_corrections' => $lineCorrections,
        ];
    }

    /**
     * Generate Z-report data for a session
     */
    public static function generateZReport(PosSession $session): array
    {
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $report = self::generateXReport($session);
        
        // Add Z-report specific data
        $report['closed_at'] = $session->closed_at;
        $report['actual_cash'] = $session->actual_cash;
        $report['cash_difference'] = $session->cash_difference;
        $report['closing_notes'] = $session->closing_notes;
        $report['report_type'] = 'Z-Report';
        
        // Event summary with Norwegian translations
        $eventSummary = $session->events->groupBy('event_code')->map(function ($group) {
            $firstEvent = $group->first();
            return [
                'code' => $firstEvent->event_code,
                'description' => self::translateEventToNorwegian($firstEvent->event_code, $firstEvent->description ?? $firstEvent->event_description ?? 'N/A'),
                'count' => $group->count(),
            ];
        });
        $report['event_summary'] = $eventSummary;
        
        // Get settings to check if tips are enabled
        $settings = \App\Models\Setting::getForStore($session->store_id);
        $tipsEnabled = (bool) ($settings->tips_enabled ?? true);
        
        // Check if session spans multiple days
        $sessionStartDate = $session->opened_at->format('Y-m-d');
        $sessionEndDate = $session->closed_at ? $session->closed_at->format('Y-m-d') : now()->format('Y-m-d');
        $spansMultipleDays = $sessionStartDate !== $sessionEndDate;
        
        // Complete transaction list with all details
        $report['complete_transaction_list'] = $session->charges->where('status', 'succeeded')->map(function ($charge) use ($tipsEnabled, $spansMultipleDays) {
            $transactionDate = $charge->paid_at ?? $charge->created_at;
            return [
                'id' => $charge->id,
                'stripe_charge_id' => $charge->stripe_charge_id,
                'amount' => $charge->amount,
                'currency' => $charge->currency,
                'payment_method' => $charge->payment_method,
                'payment_code' => $charge->payment_code,
                'transaction_code' => $charge->transaction_code,
                'tip_amount' => $tipsEnabled ? $charge->tip_amount : 0,
                'description' => $charge->description,
                'paid_at' => $charge->paid_at?->toISOString(),
                'created_at' => $charge->created_at->toISOString(),
                'transaction_date' => $transactionDate->format('Y-m-d'),
                'spans_multiple_days' => $spansMultipleDays,
            ];
        });
        
        $report['spans_multiple_days'] = $spansMultipleDays;
        
        $report['tips_enabled'] = $tipsEnabled;
        
        // Receipt summary
        $receiptSummary = $session->receipts->groupBy('receipt_type')->map(function ($group) {
            return [
                'type' => $group->first()->receipt_type,
                'count' => $group->count(),
            ];
        });
        $report['receipt_summary'] = $receiptSummary;
        
        // Sales per category
        $report['sales_by_category'] = self::calculateSalesByCategory($session);
        
        return $report;
    }

    /**
     * Translate POS event code to Norwegian
     */
    protected static function translateEventToNorwegian(string $eventCode, string $fallback = 'N/A'): string
    {
        return match($eventCode) {
            PosEvent::EVENT_APPLICATION_START => 'POS-applikasjon startet',
            PosEvent::EVENT_APPLICATION_SHUTDOWN => 'POS-applikasjon avsluttet',
            PosEvent::EVENT_EMPLOYEE_LOGIN => 'Ansatt innlogging',
            PosEvent::EVENT_EMPLOYEE_LOGOUT => 'Ansatt utlogging',
            PosEvent::EVENT_CASH_DRAWER_OPEN => 'Kontantskuff åpnet',
            PosEvent::EVENT_CASH_DRAWER_CLOSE => 'Kontantskuff lukket',
            PosEvent::EVENT_X_REPORT => 'X-rapport (mellomrapport)',
            PosEvent::EVENT_Z_REPORT => 'Z-rapport (sluttrapport)',
            PosEvent::EVENT_SALES_RECEIPT => 'Salgskvittering',
            PosEvent::EVENT_RETURN_RECEIPT => 'Returkvittering',
            PosEvent::EVENT_VOID_TRANSACTION => 'Annullert transaksjon',
            PosEvent::EVENT_CORRECTION_RECEIPT => 'Korreksjonskvittering',
            PosEvent::EVENT_CASH_PAYMENT => 'Kontantbetaling',
            PosEvent::EVENT_CARD_PAYMENT => 'Kortbetaling',
            PosEvent::EVENT_MOBILE_PAYMENT => 'Mobilbetaling',
            PosEvent::EVENT_OTHER_PAYMENT => 'Annen betalingsmetode',
            PosEvent::EVENT_SESSION_OPENED => 'Økt åpnet',
            PosEvent::EVENT_SESSION_CLOSED => 'Økt stengt',
            default => $fallback,
        };
    }

    /**
     * Calculate sales per product category (collection)
     */
    protected static function calculateSalesByCategory(PosSession $session): \Illuminate\Support\Collection
    {
        $session->load(['receipts']);
        
        $categorySales = collect();
        
        // Get all sales receipts for this session
        $salesReceipts = $session->receipts->where('receipt_type', 'sales');
        
        // Collect all product and variant IDs to eager load
        $productIds = [];
        $variantIds = [];
        
        foreach ($salesReceipts as $receipt) {
            $items = $receipt->receipt_data['items'] ?? [];
            foreach ($items as $item) {
                if (isset($item['product_id'])) {
                    $productIds[] = (int) $item['product_id'];
                }
                if (isset($item['variant_id'])) {
                    $variantIds[] = (int) $item['variant_id'];
                }
            }
        }
        
        // Eager load products and variants with collections
        $products = \App\Models\ConnectedProduct::whereIn('id', array_unique($productIds))
            ->with('collections')
            ->get()
            ->keyBy('id');
        
        $variants = \App\Models\ProductVariant::whereIn('id', array_unique($variantIds))
            ->with('product.collections')
            ->get()
            ->keyBy('id');
        
        foreach ($salesReceipts as $receipt) {
            $items = $receipt->receipt_data['items'] ?? [];
            
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? null;
                $variantId = $item['variant_id'] ?? null;
                $quantity = $item['quantity'] ?? 1;
                
                // Calculate line total - handle both øre (integer) and decimal formats
                $lineTotal = 0;
                if (isset($item['line_total'])) {
                    $lineTotal = is_numeric($item['line_total']) ? (int) $item['line_total'] : 0;
                } elseif (isset($item['unit_price'])) {
                    $unitPrice = is_numeric($item['unit_price']) ? (int) $item['unit_price'] : 0;
                    $lineTotal = $unitPrice * $quantity;
                }
                
                // Get product to find its collections
                $product = null;
                if ($variantId && isset($variants[$variantId])) {
                    $product = $variants[$variantId]->product;
                } elseif ($productId && isset($products[$productId])) {
                    $product = $products[$productId];
                }
                
                if ($product) {
                    $collections = $product->collections;
                    
                    if ($collections->isEmpty()) {
                        // If no collections, use "Ingen kategori" (No category)
                        $categoryName = 'Ingen kategori';
                        $categoryId = 'no-category';
                    } else {
                        // Use the first collection (products can belong to multiple)
                        $collection = $collections->first();
                        $categoryName = $collection->name;
                        $categoryId = $collection->id;
                    }
                    
                    if (!$categorySales->has($categoryId)) {
                        $categorySales->put($categoryId, [
                            'id' => $categoryId,
                            'name' => $categoryName,
                            'count' => 0,
                            'amount' => 0,
                        ]);
                    }
                    
                    $current = $categorySales->get($categoryId);
                    $current['count'] += $quantity;
                    $current['amount'] += $lineTotal;
                    $categorySales->put($categoryId, $current);
                }
            }
        }
        
        return $categorySales->sortByDesc('amount')->values();
    }

    /**
     * Calculate manual discounts from session receipts
     * NOTE: Currently all discounts are treated as manual until more discount logic is implemented
     */
    protected static function calculateManualDiscounts(PosSession $session): array
    {
        $session->load(['receipts', 'charges']);
        $manualDiscountCount = 0;
        $manualDiscountAmount = 0;
        
        foreach ($session->receipts as $receipt) {
            $receiptData = $receipt->receipt_data ?? [];
            
            // If receipt_data doesn't have items, try to get from charge metadata
            if (empty($receiptData['items']) && $receipt->charge_id) {
                $charge = $session->charges->firstWhere('id', $receipt->charge_id);
                if ($charge && $charge->metadata) {
                    $metadata = is_array($charge->metadata) ? $charge->metadata : json_decode($charge->metadata ?? '{}', true);
                    if (isset($metadata['items']) && is_array($metadata['items'])) {
                        $receiptData['items'] = $metadata['items'];
                    }
                    if (isset($metadata['discounts']) && is_array($metadata['discounts'])) {
                        $receiptData['discounts'] = $metadata['discounts'];
                    }
                    if (isset($metadata['total_discounts'])) {
                        $receiptData['total_discounts'] = $metadata['total_discounts'];
                    }
                }
            }
            
            // Check item-level discounts
            // For now, all discounts are treated as manual
            $items = $receiptData['items'] ?? [];
            foreach ($items as $item) {
                // Handle discount_amount in different formats:
                // - Integer (øre): direct use
                // - String/number (formatted): try to parse
                // - May be stored as 0 or null if no discount
                $discountAmount = 0;
                if (isset($item['discount_amount'])) {
                    $discountValue = $item['discount_amount'];
                    // If it's a string, it might be formatted (e.g., "50,00" or "50.00")
                    if (is_string($discountValue)) {
                        // Remove formatting and convert to øre
                        $discountValue = str_replace([',', ' '], ['.', ''], $discountValue);
                        $discountAmount = (int) round((float) $discountValue * 100);
                    } elseif (is_numeric($discountValue)) {
                        // If it's already a number, check if it's in øre or kroner
                        // If less than 1000, assume it's already in øre, otherwise might be in kroner
                        $discountAmount = (int) $discountValue;
                        if ($discountAmount > 1000 && $discountAmount < 100000) {
                            // Likely in kroner (e.g., 50.00), convert to øre
                            $discountAmount = (int) round($discountAmount * 100);
                        }
                    }
                }
                
                // Count all discounts as manual for now
                if ($discountAmount > 0) {
                    $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                    $manualDiscountCount++;
                    $manualDiscountAmount += $discountAmount * $quantity;
                }
            }
            
            // Check cart-level discounts
            // For now, all discounts are treated as manual
            $discounts = $receiptData['discounts'] ?? [];
            foreach ($discounts as $discount) {
                // Handle discount amount in different formats
                $discountAmount = 0;
                if (isset($discount['amount'])) {
                    $discountValue = $discount['amount'];
                    // If it's a string, it might be formatted
                    if (is_string($discountValue)) {
                        $discountValue = str_replace([',', ' '], ['.', ''], $discountValue);
                        $discountAmount = (int) round((float) $discountValue * 100);
                    } elseif (is_numeric($discountValue)) {
                        $discountAmount = (int) $discountValue;
                        // If it's a large number, might be in kroner
                        if ($discountAmount > 1000 && $discountAmount < 100000) {
                            $discountAmount = (int) round($discountAmount * 100);
                        }
                    }
                }
                
                // Count all discounts as manual for now
                if ($discountAmount > 0) {
                    $manualDiscountCount++;
                    $manualDiscountAmount += $discountAmount;
                }
            }
            
            // Also check total_discounts as a fallback if individual discounts aren't found
            // This handles cases where discounts exist but aren't broken down per item
            if ($manualDiscountAmount === 0 && isset($receiptData['total_discounts'])) {
                $totalDiscounts = $receiptData['total_discounts'];
                if (is_numeric($totalDiscounts) && $totalDiscounts > 0) {
                    // Convert from kroner to øre if needed
                    $totalDiscountsOre = (int) $totalDiscounts;
                    if ($totalDiscountsOre < 1000) {
                        // Likely already in øre
                        $manualDiscountAmount = $totalDiscountsOre;
                    } else {
                        // Likely in kroner, convert to øre
                        $manualDiscountAmount = (int) round($totalDiscountsOre * 100);
                    }
                    if ($manualDiscountAmount > 0) {
                        $manualDiscountCount = 1; // At least one discount
                    }
                }
            }
        }
        
        return [
            'count' => $manualDiscountCount,
            'amount' => $manualDiscountAmount,
        ];
    }

    /**
     * Calculate line corrections from session
     * Only reductions count as line corrections (per FAQ requirement)
     */
    protected static function calculateLineCorrections(PosSession $session): array
    {
        $session->load(['lineCorrections']);
        
        $correctionsByType = $session->lineCorrections->groupBy('correction_type')->map(function ($group) {
            return [
                'type' => $group->first()->correction_type,
                'count' => $group->count(),
                'total_quantity_reduction' => $group->sum('quantity_reduction'),
                'total_amount_reduction' => $group->sum('amount_reduction'),
            ];
        });
        
        $totalCount = $session->lineCorrections->count();
        $totalAmountReduction = $session->lineCorrections->sum('amount_reduction');
        
        return [
            'total_count' => $totalCount,
            'total_amount_reduction' => $totalAmountReduction,
            'by_type' => $correctionsByType,
        ];
    }
}
