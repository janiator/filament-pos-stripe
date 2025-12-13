<?php

namespace App\Filament\Widgets;

use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class PosDailySalesTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Daily Sales Trend (Last 30 Days)';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 4,
        'xl' => 4,
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

        // Get data for the last 30 days
        $startDate = Carbon::now()->subDays(30)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Get all POS charges for the period
        $charges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        // Group by day
        $dailyData = [];
        $labels = [];
        
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            
            $dayCharges = $charges->filter(function ($charge) use ($dayStart, $dayEnd) {
                return $charge->paid_at && 
                       $charge->paid_at->gte($dayStart) && 
                       $charge->paid_at->lte($dayEnd);
            });
            
            $dailyData[] = $dayCharges->sum('amount') / 100; // Convert to currency units
            $labels[] = $date->format('M d');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sales (kr)',
                    'data' => $dailyData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
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
