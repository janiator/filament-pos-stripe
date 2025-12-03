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
        
        $totalAmount = $charges->sum('amount');
        $cashAmount = $charges->where('payment_method', 'cash')->sum('amount');
        $cardAmount = $charges->where('payment_method', 'card')->sum('amount');
        $mobileAmount = $charges->where('payment_method', 'mobile')->sum('amount');
        $otherAmount = $totalAmount - $cashAmount - $cardAmount - $mobileAmount;
        $totalTips = $charges->sum('tip_amount');
        
        // Calculate VAT (25% standard in Norway)
        $vatRate = 0.25;
        $vatBase = round($totalAmount / (1 + $vatRate), 0);
        $vatAmount = $totalAmount - $vatBase;
        
        // Payment method breakdown
        $byPaymentMethod = $charges->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
                'tips' => $group->sum('tip_amount'),
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
        $nullinnslagCount = $session->events->where('event_code', PosEvent::EVENT_CASH_DRAWER_OPEN)
            ->where('event_data->nullinnslag', true)->count();
        
        // Receipt count
        $receiptCount = $session->receipts->count();
        
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
        
        // Event summary
        $eventSummary = $session->events->groupBy('event_code')->map(function ($group) {
            $firstEvent = $group->first();
            return [
                'code' => $firstEvent->event_code,
                'description' => $firstEvent->event_description,
                'count' => $group->count(),
            ];
        });
        $report['event_summary'] = $eventSummary;
        
        // Complete transaction list with all details
        $report['complete_transaction_list'] = $session->charges->where('status', 'succeeded')->map(function ($charge) {
            return [
                'id' => $charge->id,
                'stripe_charge_id' => $charge->stripe_charge_id,
                'amount' => $charge->amount,
                'currency' => $charge->currency,
                'payment_method' => $charge->payment_method,
                'payment_code' => $charge->payment_code,
                'transaction_code' => $charge->transaction_code,
                'tip_amount' => $charge->tip_amount,
                'description' => $charge->description,
                'paid_at' => $charge->paid_at?->toISOString(),
                'created_at' => $charge->created_at->toISOString(),
            ];
        });
        
        // Receipt summary
        $receiptSummary = $session->receipts->groupBy('receipt_type')->map(function ($group) {
            return [
                'type' => $group->first()->receipt_type,
                'count' => $group->count(),
            ];
        });
        $report['receipt_summary'] = $receiptSummary;
        
        return $report;
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
