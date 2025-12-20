<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class AverageTransactionValueWidget extends BaseWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 3,
        'xl' => 3,
    ];

    protected function getStats(): array
    {
        $store = Filament::getTenant();
        
        if (!$store) {
            return [];
        }

        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Get charges for the period
        $charges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $totalSales = $charges->sum('amount');
        $totalTransactions = $charges->count();
        
        $avgTransactionValue = $totalTransactions > 0 
            ? round($totalSales / $totalTransactions / 100, 2)
            : 0;

        // Calculate previous period for comparison
        $periodLength = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($periodLength + 1)->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();

        $previousCharges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$previousStartDate, $previousEndDate])
            ->get();

        $previousTotalSales = $previousCharges->sum('amount');
        $previousTotalTransactions = $previousCharges->count();
        $previousAvgValue = $previousTotalTransactions > 0
            ? round($previousTotalSales / $previousTotalTransactions / 100, 2)
            : 0;

        $change = $previousAvgValue > 0
            ? round((($avgTransactionValue - $previousAvgValue) / $previousAvgValue) * 100, 1)
            : ($avgTransactionValue > 0 ? 100 : 0);

        // Calculate min and max transaction values
        $minTransaction = $charges->min('amount') / 100 ?? 0;
        $maxTransaction = $charges->max('amount') / 100 ?? 0;

        return [
            Stat::make('Average Transaction Value', number_format($avgTransactionValue, 2) . ' kr')
                ->description($change >= 0 ? $change . '% increase' : abs($change) . '% decrease')
                ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($change >= 0 ? 'success' : 'danger'),

            Stat::make('Min Transaction', number_format($minTransaction, 2) . ' kr')
                ->description('Lowest transaction in period')
                ->color('info'),

            Stat::make('Max Transaction', number_format($maxTransaction, 2) . ' kr')
                ->description('Highest transaction in period')
                ->color('success'),
        ];
    }
}

