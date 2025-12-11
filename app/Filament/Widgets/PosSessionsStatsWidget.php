<?php

namespace App\Filament\Widgets;

use App\Models\PosSession;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PosSessionsStatsWidget extends BaseWidget
{
    protected ?string $heading = 'POS Sessions Statistics';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $store = Filament::getTenant();
        
        if (!$store) {
            return [];
        }

        // Get data for today
        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();

        // Get today's sessions
        $todaySessions = PosSession::where('store_id', $store->id)
            ->whereBetween('opened_at', [$todayStart, $todayEnd])
            ->get();

        $todayOpen = $todaySessions->where('status', 'open')->count();
        $todayClosed = $todaySessions->where('status', 'closed')->count();
        $todayTotal = $todaySessions->count();

        // Get all open sessions
        $allOpenSessions = PosSession::where('store_id', $store->id)
            ->where('status', 'open')
            ->get();

        $totalOpen = $allOpenSessions->count();

        // Calculate average session duration for closed sessions today
        $closedSessions = $todaySessions->where('status', 'closed')
            ->filter(function ($session) {
                return $session->opened_at && $session->closed_at;
            });

        $avgDuration = 0;
        if ($closedSessions->count() > 0) {
            $totalMinutes = $closedSessions->sum(function ($session) {
                return $session->opened_at->diffInMinutes($session->closed_at);
            });
            $avgDuration = round($totalMinutes / $closedSessions->count());
        }

        // Calculate total sales from today's closed sessions
        $todaySales = $todaySessions->where('status', 'closed')
            ->sum('total_amount') / 100; // Convert to currency units

        // Calculate average sales per closed session
        $avgSalesPerSession = $closedSessions->count() > 0
            ? round($todaySales / $closedSessions->count(), 2)
            : 0;

        // Calculate daily session count for chart (last 7 days)
        $dailySessions = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->startOfDay();
            $daySessions = PosSession::where('store_id', $store->id)
                ->whereDate('opened_at', $date)
                ->count();
            $dailySessions[] = $daySessions;
        }

        return [
            Stat::make('Open Sessions Today', $todayOpen)
                ->description($totalOpen . ' total open sessions')
                ->descriptionIcon('heroicon-m-clock')
                ->chart($dailySessions)
                ->color($todayOpen > 0 ? 'warning' : 'success'),

            Stat::make('Closed Sessions Today', $todayClosed)
                ->description($todayTotal . ' total sessions today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart($dailySessions)
                ->color('success'),

            Stat::make('Average Session Duration', $avgDuration > 0 ? $avgDuration . ' min' : 'N/A')
                ->description('Average for closed sessions today')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),

            Stat::make('Average Sales per Session', $avgSalesPerSession > 0 ? number_format($avgSalesPerSession, 2) . ' kr' : 'N/A')
                ->description('Based on closed sessions today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}
