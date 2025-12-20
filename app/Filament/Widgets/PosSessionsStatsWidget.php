<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PosSessionsStatsWidget extends BaseWidget
{
    use HasDashboardDateRange;

    protected ?string $heading = 'POS Sessions Statistics';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $store = Filament::getTenant();
        
        if (!$store) {
            return [];
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

        // Calculate total sales
        $totalSales = $charges->sum('amount') / 100; // Convert to currency units

        // Calculate number of days in the period
        // Count actual days by iterating through them (more accurate than diffInDays)
        $daysDiff = 0;
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $daysDiff++;
            $currentDate->addDay();
        }

        // Calculate average sales per day
        $avgSalesPerDay = $daysDiff > 0
            ? round($totalSales / $daysDiff, 2)
            : 0;

        // Calculate daily sales for chart
        $dailySales = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();
            
            $dayCharges = $charges->filter(function ($charge) use ($dayStart, $dayEnd) {
                return $charge->paid_at && 
                       $charge->paid_at->gte($dayStart) && 
                       $charge->paid_at->lte($dayEnd);
            });
            
            $dailySales[] = $dayCharges->sum('amount') / 100; // Convert to currency units
            
            $currentDate->addDay();
        }

        return [
            Stat::make('Average Sales per Day', number_format($avgSalesPerDay, 2) . ' kr')
                ->description('Based on selected period (' . $daysDiff . ' days)')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($dailySales)
                ->color('success'),
        ];
    }
}
