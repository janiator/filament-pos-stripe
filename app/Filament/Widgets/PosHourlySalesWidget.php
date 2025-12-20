<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PosHourlySalesWidget extends ChartWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 4,
        'xl' => 4,
    ];

    public function getHeading(): string
    {
        return "Hourly Sales Distribution";
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
            ],
        ];
    }
}
