<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class RefundsOverviewWidget extends BaseWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 7;

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

        // Get refunded charges
        $refundedCharges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('refunded', true)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $totalRefunded = $refundedCharges->sum('amount_refunded');
        $refundCount = $refundedCharges->count();

        // Get all charges for the period to calculate refund rate
        $allCharges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $totalSales = $allCharges->sum('amount');
        $totalTransactions = $allCharges->count();
        
        $refundRate = $totalSales > 0
            ? round(($totalRefunded / $totalSales) * 100, 2)
            : 0;

        $refundPercentage = $totalTransactions > 0
            ? round(($refundCount / $totalTransactions) * 100, 2)
            : 0;

        // Calculate previous period for comparison
        $periodLength = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($periodLength + 1)->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();

        $previousRefunded = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('refunded', true)
            ->whereBetween('paid_at', [$previousStartDate, $previousEndDate])
            ->sum('amount_refunded');

        $refundChange = $previousRefunded > 0
            ? round((($totalRefunded - $previousRefunded) / $previousRefunded) * 100, 1)
            : ($totalRefunded > 0 ? 100 : 0);

        return [
            Stat::make('Total Refunds', number_format($totalRefunded / 100, 2) . ' kr')
                ->description($refundChange >= 0 ? $refundChange . '% increase' : abs($refundChange) . '% decrease')
                ->descriptionIcon($refundChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($refundChange >= 0 ? 'danger' : 'success'),

            Stat::make('Refund Rate', $refundRate . '%')
                ->description('Percentage of sales refunded')
                ->color($refundRate > 5 ? 'danger' : ($refundRate > 2 ? 'warning' : 'success')),

            Stat::make('Refund Count', number_format($refundCount))
                ->description($refundPercentage . '% of transactions')
                ->color('info'),
        ];
    }
}

