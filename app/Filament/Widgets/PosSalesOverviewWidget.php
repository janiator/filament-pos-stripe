<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use App\Models\PosSession;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PosSalesOverviewWidget extends BaseWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 1;

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
        
        $daysDiff = $startDate->diffInDays($endDate);

        // Get all POS charges for the period
        $charges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        // Calculate totals
        $totalSales = $charges->sum('amount');
        $totalTransactions = $charges->count();
        
        // Get sessions for the period
        $sessions = PosSession::where('store_id', $store->id)
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->get();
        
        $totalSessions = $sessions->count();
        $openSessions = $sessions->where('status', 'open')->count();

        // Calculate daily sales for chart
        $dailySales = [];
        $dailyTransactions = [];
        $dailySessions = [];
        
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();
            
            $dayCharges = $charges->filter(function ($charge) use ($dayStart, $dayEnd) {
                return $charge->paid_at && 
                       $charge->paid_at->gte($dayStart) && 
                       $charge->paid_at->lte($dayEnd);
            });
            
            $daySessions = $sessions->filter(function ($session) use ($dayStart, $dayEnd) {
                return $session->opened_at && 
                       $session->opened_at->gte($dayStart) && 
                       $session->opened_at->lte($dayEnd);
            });
            
            $dailySales[] = round($dayCharges->sum('amount') / 100, 0);
            $dailyTransactions[] = $dayCharges->count();
            $dailySessions[] = $daySessions->count();
            
            $currentDate->addDay();
        }

        // Calculate change from previous period (same length)
        $periodLength = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($periodLength + 1)->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        
        $previousCharges = ConnectedCharge::where('stripe_account_id', $store->stripe_account_id)
            ->whereNotNull('pos_session_id')
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$previousStartDate, $previousEndDate])
            ->get();
        
        $previousSales = $previousCharges->sum('amount');
        $previousTransactions = $previousCharges->count();
        
        $previousSessions = PosSession::where('store_id', $store->id)
            ->whereBetween('opened_at', [$previousStartDate, $previousEndDate])
            ->count();

        $salesChange = $previousSales > 0 
            ? round((($totalSales - $previousSales) / $previousSales) * 100, 1)
            : ($totalSales > 0 ? 100 : 0);
        
        $transactionsChange = $previousTransactions > 0
            ? round((($totalTransactions - $previousTransactions) / $previousTransactions) * 100, 1)
            : ($totalTransactions > 0 ? 100 : 0);

        $sessionsChange = $previousSessions > 0
            ? round((($totalSessions - $previousSessions) / $previousSessions) * 100, 1)
            : ($totalSessions > 0 ? 100 : 0);

        $periodLabel = $this->getPeriodLabel($startDate, $endDate);
        
        return [
            Stat::make('Total Sales', number_format($totalSales / 100, 2) . ' kr')
                ->description($salesChange >= 0 ? $salesChange . '% increase' : abs($salesChange) . '% decrease')
                ->descriptionIcon($salesChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($dailySales)
                ->color($salesChange >= 0 ? 'success' : 'danger'),

            Stat::make('Total Transactions', number_format($totalTransactions))
                ->description($transactionsChange >= 0 ? $transactionsChange . '% increase' : abs($transactionsChange) . '% decrease')
                ->descriptionIcon($transactionsChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($dailyTransactions)
                ->color($transactionsChange >= 0 ? 'success' : 'warning'),

            Stat::make('POS Sessions', number_format($totalSessions) . ' (' . $openSessions . ' open)')
                ->description($sessionsChange >= 0 ? $sessionsChange . '% increase' : abs($sessionsChange) . '% decrease')
                ->descriptionIcon($sessionsChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($dailySessions)
                ->color('info'),
        ];
    }

    protected function getPeriodLabel(Carbon $start, Carbon $end): string
    {
        $days = round($start->diffInDays($end));
        if ($days === 0) {
            return 'Today';
        }
        if ($days === 1) {
            return 'Yesterday';
        }
        return "Last {$days} days";
    }
}



