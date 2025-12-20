<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

trait HasDashboardDateRange
{
    use InteractsWithPageFilters;

    protected function getDateRange(): array
    {
        // Get date range from page filters
        $filters = $this->pageFilters ?? [];
        $dateRange = $filters['dateRange'] ?? '7d';
        
        if ($dateRange === 'custom') {
            $startDate = $filters['startDate'] ?? null;
            $endDate = $filters['endDate'] ?? null;
            
            if ($startDate && $endDate) {
                return [
                    'start' => Carbon::parse($startDate)->startOfDay(),
                    'end' => Carbon::parse($endDate)->endOfDay(),
                ];
            }
            // Fallback to last 7 days if custom but dates not set
            $dateRange = '7d';
        }

        // Convert preset ranges to dates
        // Note: For "Last X Days", we use subDays(X-1) to get exactly X days including today
        return match($dateRange) {
            'today' => [
                'start' => Carbon::today()->startOfDay(),
                'end' => Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ],
            '7d' => [
                'start' => Carbon::now()->subDays(6)->startOfDay(), // 6 days ago + today = 7 days
                'end' => Carbon::now()->endOfDay(),
            ],
            '30d' => [
                'start' => Carbon::now()->subDays(29)->startOfDay(), // 29 days ago + today = 30 days
                'end' => Carbon::now()->endOfDay(),
            ],
            '90d' => [
                'start' => Carbon::now()->subDays(89)->startOfDay(), // 89 days ago + today = 90 days
                'end' => Carbon::now()->endOfDay(),
            ],
            'this_month' => [
                'start' => Carbon::now()->startOfMonth()->startOfDay(),
                'end' => Carbon::now()->endOfDay(),
            ],
            'last_month' => [
                'start' => Carbon::now()->subMonth()->startOfMonth()->startOfDay(),
                'end' => Carbon::now()->subMonth()->endOfMonth()->endOfDay(),
            ],
            'this_year' => [
                'start' => Carbon::now()->startOfYear()->startOfDay(),
                'end' => Carbon::now()->endOfDay(),
            ],
            default => [
                'start' => Carbon::now()->subDays(6)->startOfDay(), // 6 days ago + today = 7 days
                'end' => Carbon::now()->endOfDay(),
            ],
        };
    }

    protected function getStartDate(): Carbon
    {
        $range = $this->getDateRange();
        return $range['start'];
    }

    protected function getEndDate(): Carbon
    {
        $range = $this->getDateRange();
        return $range['end'];
    }
}

