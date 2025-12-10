<?php

namespace App\Filament\Resources\PosReports\Pages;

use App\Filament\Resources\PosReports\PosReportResource;
use Filament\Resources\Pages\Page;
use App\Models\Store;
use App\Models\PosSession;
use App\Models\ConnectedCharge;
use App\Models\PosEvent;
use App\Models\PosSessionClosing;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class PosReports extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string $resource = PosReportResource::class;

    protected string $view = 'filament.resources.pos-reports.pages.pos-reports';

    public ?string $fromDate = null;
    public ?string $toDate = null;
    public ?int $sessionId = null;

    public function mount(): void
    {
        $this->fromDate = now()->startOfDay()->format('Y-m-d');
        $this->toDate = now()->endOfDay()->format('Y-m-d');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportCsv'),
            Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action('exportPdf'),
            Action::make('generate_saf_t')
                ->label('Generate SAF-T')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action('generateSafT'),
        ];
    }

    public function table(Table $table): Table
    {
        $store = \Filament\Facades\Filament::getTenant();
        
        return $table
            ->query(
                PosSession::query()
                    ->where('store_id', $store->id)
                    ->whereDate('opened_at', '>=', $this->fromDate ?? now()->startOfDay())
                    ->whereDate('opened_at', '<=', $this->toDate ?? now()->endOfDay())
                    ->with(['charges', 'user', 'posDevice'])
            )
            ->columns([
                TextColumn::make('session_number')
                    ->label('Session #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Cashier')
                    ->searchable(),
                TextColumn::make('opened_at')
                    ->label('Opened')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Closed')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('transaction_count')
                    ->label('Transactions')
                    ->counts('charges')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('nok', divideBy: 100)
                    ->sortable(),
                TextColumn::make('expected_cash')
                    ->label('Expected Cash')
                    ->money('nok', divideBy: 100)
                    ->sortable(),
                TextColumn::make('actual_cash')
                    ->label('Actual Cash')
                    ->money('nok', divideBy: 100)
                    ->sortable(),
                TextColumn::make('cash_difference')
                    ->label('Difference')
                    ->money('nok', divideBy: 100)
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'warning' : 'success'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('view_x_report')
                    ->label('X-Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->modalHeading('X-Report (Interim Report)')
                    ->before(function (PosSession $record) {
                        // Generate report data first
                        $report = $this->generateXReport($record);
                        
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
                        'report' => $this->generateXReport($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (PosSession $record) => $record->status === 'open'),
                \Filament\Actions\Action::make('view_z_report')
                    ->label('Z-Report')
                    ->icon('heroicon-o-document-check')
                    ->modalHeading('Z-Report (End-of-Day Report)')
                    ->before(function (PosSession $record) {
                        // Generate report data first
                        $report = $this->generateZReport($record);
                        
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
                        'report' => $this->generateZReport($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (PosSession $record) => $record->status === 'closed'),
            ])
            ->defaultSort('opened_at', 'desc');
    }

    protected function generateXReport(PosSession $session): array
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
        $manualDiscounts = $this->calculateManualDiscounts($session);
        
        // Line corrections (only reductions count)
        $lineCorrections = $this->calculateLineCorrections($session);
        
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
            'sales_by_category' => $this->calculateSalesByCategory($session),
            'manual_discounts' => $manualDiscounts,
            'line_corrections' => $lineCorrections,
        ];
    }

    protected function generateZReport(PosSession $session): array
    {
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);
        $report = $this->generateXReport($session);
        
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
                'description' => $this->translateEventToNorwegian($firstEvent->event_code, $firstEvent->description ?? $firstEvent->event_description ?? 'N/A'),
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
        $report['sales_by_category'] = $this->calculateSalesByCategory($session);
        
        return $report;
    }

    /**
     * Translate POS event code to Norwegian
     */
    protected function translateEventToNorwegian(string $eventCode, string $fallback = 'N/A'): string
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
    protected function calculateSalesByCategory(PosSession $session): \Illuminate\Support\Collection
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
    protected function calculateManualDiscounts(PosSession $session): array
    {
        $session->load(['receipts']);
        $manualDiscountCount = 0;
        $manualDiscountAmount = 0;
        
        foreach ($session->receipts as $receipt) {
            $receiptData = $receipt->receipt_data ?? [];
            
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
    protected function calculateLineCorrections(PosSession $session): array
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

    public function getSalesOverview(): array
    {
        $store = \Filament\Facades\Filament::getTenant();
        
        $fromDate = Carbon::parse($this->fromDate ?? now()->startOfDay());
        $toDate = Carbon::parse($this->toDate ?? now()->endOfDay());
        
        $sessions = PosSession::where('store_id', $store->id)
            ->whereDate('opened_at', '>=', $fromDate)
            ->whereDate('opened_at', '<=', $toDate)
            ->where('status', 'closed')
            ->with('charges')
            ->get();
        
        $allCharges = $sessions->flatMap->charges->where('status', 'succeeded');
        
        $totalAmount = $allCharges->sum('amount');
        $totalTransactions = $allCharges->count();
        $totalSessions = $sessions->count();
        
        $byPaymentMethod = $allCharges->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        });
        
        $byDay = $sessions->groupBy(function ($session) {
            return $session->opened_at->format('Y-m-d');
        })->map(function ($daySessions) {
            $charges = $daySessions->flatMap->charges->where('status', 'succeeded');
            return [
                'sessions' => $daySessions->count(),
                'transactions' => $charges->count(),
                'amount' => $charges->sum('amount'),
            ];
        });
        
        return [
            'period' => [
                'from' => $fromDate->format('d.m.Y'),
                'to' => $toDate->format('d.m.Y'),
            ],
            'totals' => [
                'sessions' => $totalSessions,
                'transactions' => $totalTransactions,
                'amount' => $totalAmount,
            ],
            'by_payment_method' => $byPaymentMethod,
            'by_day' => $byDay,
        ];
    }

    public function exportCsv(): void
    {
        $store = \Filament\Facades\Filament::getTenant();
        $fromDate = Carbon::parse($this->fromDate ?? now()->startOfDay());
        $toDate = Carbon::parse($this->toDate ?? now()->endOfDay());
        
        $sessions = PosSession::where('store_id', $store->id)
            ->whereDate('opened_at', '>=', $fromDate)
            ->whereDate('opened_at', '<=', $toDate)
            ->with(['charges', 'user'])
            ->get();
        
        $filename = sprintf('pos-reports-%s-%s-%s.csv', $store->slug, $fromDate->format('Y-m-d'), $toDate->format('Y-m-d'));
        $path = 'exports/' . $filename;
        
        $file = fopen(storage_path('app/' . $path), 'w');
        
        // Header
        fputcsv($file, [
            'Session Number',
            'Status',
            'Cashier',
            'Opened At',
            'Closed At',
            'Transactions',
            'Total Amount (NOK)',
            'Cash Amount (NOK)',
            'Card Amount (NOK)',
            'Expected Cash (NOK)',
            'Actual Cash (NOK)',
            'Cash Difference (NOK)',
        ]);
        
        // Data
        foreach ($sessions as $session) {
            $charges = $session->charges->where('status', 'succeeded');
            $cashAmount = $charges->where('payment_method', 'cash')->sum('amount');
            $cardAmount = $charges->where('payment_method', 'card')->sum('amount');
            
            fputcsv($file, [
                $session->session_number,
                $session->status,
                $session->user?->name ?? 'N/A',
                $session->opened_at->format('Y-m-d H:i:s'),
                $session->closed_at?->format('Y-m-d H:i:s') ?? '',
                $charges->count(),
                $charges->sum('amount') / 100,
                $cashAmount / 100,
                $cardAmount / 100,
                ($session->expected_cash ?? 0) / 100,
                ($session->actual_cash ?? 0) / 100,
                ($session->cash_difference ?? 0) / 100,
            ]);
        }
        
        fclose($file);
        
        Notification::make()
            ->title('CSV Export Generated')
            ->success()
            ->body("File saved: {$filename}")
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download')
                    ->url(Storage::url($path))
                    ->openUrlInNewTab(),
            ])
            ->send();
    }

    public function exportPdf(): void
    {
        // TODO: Implement PDF export using a PDF library like dompdf or snappy
        Notification::make()
            ->title('PDF Export')
            ->warning()
            ->body('PDF export will be implemented soon')
            ->send();
    }

    public function generateSafT(): void
    {
        $store = \Filament\Facades\Filament::getTenant();
        $fromDate = Carbon::parse($this->fromDate ?? now()->startOfDay());
        $toDate = Carbon::parse($this->toDate ?? now()->endOfDay());
        
        try {
            $generator = new \App\Actions\SafT\GenerateSafTCashRegister();
            $xmlContent = $generator($store, $fromDate, $toDate);
            
            $filename = sprintf('SAF-T_%s_%s_%s.xml', $store->slug, $fromDate->format('Y-m-d'), $toDate->format('Y-m-d'));
            $path = 'saf-t/' . $filename;
            Storage::put($path, $xmlContent);
            
            Notification::make()
                ->title('SAF-T File Generated')
                ->success()
                ->body("File saved: {$filename}")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('download')
                        ->label('Download')
                        ->url(Storage::url($path))
                        ->openUrlInNewTab(),
                ])
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error Generating SAF-T')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }
}
