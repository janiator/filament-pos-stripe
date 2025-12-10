<?php

namespace App\Filament\Widgets;

use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PosSalesByPaymentMethodWidget extends ChartWidget
{
    protected ?string $heading = 'Sales by Payment Method (Last 7 Days)';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $store = Filament::getTenant();
        
        if (!$store) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get data for the last 7 days
        $startDate = Carbon::now()->subDays(7)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

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
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const label = context.label || "";
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return label + ": " + value.toLocaleString("nb-NO") + " kr (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
        ];
    }
}
