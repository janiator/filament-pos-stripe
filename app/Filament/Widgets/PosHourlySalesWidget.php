<?php

namespace App\Filament\Widgets;

use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PosHourlySalesWidget extends ChartWidget
{
    protected ?string $heading = 'Hourly Sales Distribution (Last 7 Days)';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
    ];

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

        // Group by hour (0-23)
        $hourlyData = [];
        $labels = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            $hourCharges = $charges->filter(function ($charge) use ($hour) {
                return $charge->paid_at && $charge->paid_at->hour === $hour;
            });
            
            $hourlyData[] = $hourCharges->sum('amount') / 100; // Convert to currency units
            $labels[] = sprintf('%02d:00', $hour);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales (kr)',
                    'data' => $hourlyData,
                    'backgroundColor' => 'rgba(251, 191, 36, 0.6)',
                    'borderColor' => 'rgb(251, 191, 36)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) {
                            return value.toLocaleString("nb-NO") + " kr";
                        }',
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                        'minRotation' => 45,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return "Sales: " + context.parsed.y.toLocaleString("nb-NO") + " kr";
                        }',
                    ],
                ],
            ],
        ];
    }
}
