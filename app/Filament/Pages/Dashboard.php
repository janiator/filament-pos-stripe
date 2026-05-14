<?php

namespace App\Filament\Pages;

use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function mountCanAuthorizeAccess(): void
    {
        if (static::canAccess()) {
            return;
        }

        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();
        $panel = Filament::getCurrentPanel();

        if ($user instanceof User && $tenant instanceof Store) {
            $dashboardUrl = static::getUrl([], true, $panel->getId(), $tenant);
            $target = $user->getFilamentHomeUrl($panel, $tenant);

            if ($target !== null && $target !== $dashboardUrl) {
                $this->redirect($target);

                return;
            }
        }

        abort(403);
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && $user->can('View:Dashboard');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dateRange')
                    ->label(__('Time Range'))
                    ->options([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        '7d' => 'Last 7 Days',
                        '30d' => 'Last 30 Days',
                        '90d' => 'Last 90 Days',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'this_year' => 'This Year',
                        'custom' => 'Custom Range',
                    ])
                    ->default('7d')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state !== 'custom') {
                            $set('startDate', null);
                            $set('endDate', null);
                        }
                    }),
                DatePicker::make('startDate')
                    ->label(__('Start Date'))
                    ->visible(fn ($get) => $get('dateRange') === 'custom')
                    ->live(),
                DatePicker::make('endDate')
                    ->label(__('End Date'))
                    ->visible(fn ($get) => $get('dateRange') === 'custom')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        if ($state && ! $get('startDate')) {
                            $set('startDate', Carbon::parse($state)->subDays(7)->format('Y-m-d'));
                        }
                    }),
            ]);
    }

    public function getDateRange(): array
    {
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
        return match ($dateRange) {
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

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 1,
            'lg' => 12,
            'xl' => 12,
            '2xl' => 12,
        ];
    }
}
