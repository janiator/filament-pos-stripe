<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCustomer;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CustomerGrowthWidget extends ChartWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 4,
        'xl' => 4,
    ];

    public function getHeading(): string
    {
        return "Customer Growth";
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

        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Get customers created in the period, grouped by day
        $customers = ConnectedCustomer::where('stripe_account_id', $store->stripe_account_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Group by day
        $dailyData = [];
        $labels = [];
        $cumulativeData = [];
        $totalSoFar = 0;
        
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();
            
            $dayCustomers = $customers->filter(function ($customer) use ($dayStart, $dayEnd) {
                return $customer->created_at && 
                       $customer->created_at->gte($dayStart) && 
                       $customer->created_at->lte($dayEnd);
            });
            
            $dayCount = $dayCustomers->count();
            $totalSoFar += $dayCount;
            
            $dailyData[] = $dayCount;
            $cumulativeData[] = $totalSoFar;
            $labels[] = $currentDate->format('M d');
            
            $currentDate->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Customers',
                    'data' => $dailyData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Cumulative Total',
                    'data' => $cumulativeData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'fill' => false,
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
                        'stepSize' => 1,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
        ];
    }
}

