<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TopProductsWidget extends ChartWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 4,
        'xl' => 4,
    ];

    public function getHeading(): string
    {
        return "Top Products";
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

        // Aggregate products from charge metadata
        $productStats = [];
        
        foreach ($charges as $charge) {
            $metadata = $charge->metadata ?? [];
            $items = $metadata['items'] ?? [];
            
            foreach ($items as $item) {
                $productId = $item['product_id'] ?? $item['stripe_product_id'] ?? null;
                $productName = $item['name'] ?? $item['product_name'] ?? 'Unknown Product';
                $quantity = $item['quantity'] ?? 1;
                $price = $item['price'] ?? $item['unit_price'] ?? 0;
                
                // Handle price in different formats (could be in cents or decimal)
                if ($price > 10000) {
                    $price = $price / 100; // Convert from cents
                }
                
                $lineTotal = $price * $quantity;
                
                if ($productId || $productName !== 'Unknown Product') {
                    $key = $productId ?: $productName;
                    
                    if (!isset($productStats[$key])) {
                        $productStats[$key] = [
                            'name' => $productName,
                            'quantity' => 0,
                            'revenue' => 0,
                            'transactions' => 0,
                        ];
                    }
                    
                    $productStats[$key]['quantity'] += $quantity;
                    $productStats[$key]['revenue'] += $lineTotal;
                    $productStats[$key]['transactions'] += 1;
                }
            }
        }

        // Sort by revenue and take top 10
        usort($productStats, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        
        $topProducts = array_slice($productStats, 0, 10);

        if (empty($topProducts)) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Prepare data for chart
        $labels = array_map(fn($product) => $product['name'], $topProducts);
        $revenues = array_map(fn($product) => round($product['revenue'], 2), $topProducts);
        $quantities = array_map(fn($product) => $product['quantity'], $topProducts);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (kr)',
                    'data' => $revenues,
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',   // Green
                        'rgba(59, 130, 246, 0.8)',  // Blue
                        'rgba(251, 191, 36, 0.8)',  // Amber
                        'rgba(239, 68, 68, 0.8)',   // Red
                        'rgba(168, 85, 247, 0.8)',  // Purple
                        'rgba(236, 72, 153, 0.8)',  // Pink
                        'rgba(20, 184, 166, 0.8)',  // Teal
                        'rgba(245, 158, 11, 0.8)',  // Orange
                        'rgba(139, 92, 246, 0.8)',  // Indigo
                        'rgba(14, 165, 233, 0.8)',  // Sky
                    ],
                    'borderColor' => [
                        'rgb(34, 197, 94)',
                        'rgb(59, 130, 246)',
                        'rgb(251, 191, 36)',
                        'rgb(239, 68, 68)',
                        'rgb(168, 85, 247)',
                        'rgb(236, 72, 153)',
                        'rgb(20, 184, 166)',
                        'rgb(245, 158, 11)',
                        'rgb(139, 92, 246)',
                        'rgb(14, 165, 233)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
            'quantities' => $quantities, // Store quantities for potential future use
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // Horizontal bar chart
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}

