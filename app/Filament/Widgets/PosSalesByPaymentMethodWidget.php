<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PosSalesByPaymentMethodWidget extends ChartWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 4,
        'xl' => 4,
    ];

    public function getHeading(): string
    {
        return "Sales by Payment Method";
    }

    protected function getData(): array
    {
        $store = Filament::getTenant();
        
        if (!$store) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get date range from dashboard
        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Get all POS charges for the period
        $charges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        // Group by payment method
        $byPaymentMethod = $charges->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount') / 100, // Convert to currency units
            ];
        })->sortByDesc('amount');

        // Prepare data for chart
        $labels = $byPaymentMethod->keys()->map(function ($method) {
            // Format payment method names nicely
            return match($method) {
                'cash' => 'Cash',
                'card_present' => 'Card',
                'card' => 'Card',
                'mobile' => 'Mobile',
                'vipps' => 'Vipps',
                default => ucfirst(str_replace('_', ' ', $method)),
            };
        })->toArray();

        $amounts = $byPaymentMethod->pluck('amount')->toArray();
        $counts = $byPaymentMethod->pluck('count')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Sales Amount (kr)',
                    'data' => $amounts,
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Green
                        'rgba(59, 130, 246, 0.8)',  // Blue
                        'rgba(251, 191, 36, 0.8)',  // Amber
                        'rgba(239, 68, 68, 0.8)',   // Red
                        'rgba(168, 85, 247, 0.8)',  // Purple
                        'rgba(236, 72, 153, 0.8)',  // Pink
                    ],
                    'borderColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(59, 130, 246)',
                        'rgb(251, 191, 36)',
                        'rgb(239, 68, 68)',
                        'rgb(168, 85, 247)',
                        'rgb(236, 72, 153)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
            'transactionCounts' => $counts, // Store at chart data level
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
