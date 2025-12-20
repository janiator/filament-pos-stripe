<?php

namespace App\Filament\Widgets;

use App\Models\ConnectedCharge;
use App\Models\PosSession;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PosSalesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $store = Filament::getTenant();
        
        if (!$store) {
            return [];
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

        // Calculate totals
        $totalSales = $charges->sum('amount');
        $totalTransactions = $charges->count();
        
        // Get sessions for the period
        $sessions = PosSession::where('store_id', $store->id)
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->get();
        
        $totalSessions = $sessions->count();
        $openSessions = $sessions->where('status', 'open')->count();

        // Calculate daily sales for chart (last 7 days)
        $dailySales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->startOfDay();
            $daySales = $charges->filter(function ($charge) use ($date) {
                return $charge->paid_at && $charge->paid_at->isSameDay($date);
            })->sum('amount') / 100; // Convert to currency units
            $dailySales[] = round($daySales, 0);
        }

        // Calculate daily transactions for chart
        $dailyTransactions = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->startOfDay();
            $dayTransactions = $charges->filter(function ($charge) use ($date) {
                return $charge->paid_at && $charge->paid_at->isSameDay($date);
            })->count();
            $dailyTransactions[] = $dayTransactions;
        }

        // Calculate daily sessions for chart
        $dailySessions = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->startOfDay();
            $daySessions = $sessions->filter(function ($session) use ($date) {
                return $session->opened_at && $session->opened_at->isSameDay($date);
            })->count();
            $dailySessions[] = $daySessions;
        }

        // Calculate change from previous period
        $previousStartDate = Carbon::now()->subDays(14)->startOfDay();
        $previousEndDate = Carbon::now()->subDays(7)->endOfDay();
        
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

        return [
            Stat::make('Total Sales (7 days)', number_format($totalSales / 100, 2) . ' kr')
                ->description($salesChange >= 0 ? $salesChange . '% increase' : abs($salesChange) . '% decrease')
                ->descriptionIcon($salesChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($dailySales)
                ->color($salesChange >= 0 ? 'success' : 'danger'),

            Stat::make('Total Transactions (7 days)', number_format($totalTransactions))
                ->description($transactionsChange >= 0 ? $transactionsChange . '% increase' : abs($transactionsChange) . '% decrease')
                ->descriptionIcon($transactionsChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($dailyTransactions)
                ->color($transactionsChange >= 0 ? 'success' : 'warning'),

            Stat::make('POS Sessions (7 days)', number_format($totalSessions) . ' (' . $openSessions . ' open)')
                ->description($sessionsChange >= 0 ? $sessionsChange . '% increase' : abs($sessionsChange) . '% decrease')
                ->descriptionIcon($sessionsChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->chart($dailySessions)
                ->color('info'),
        ];
    }
}



