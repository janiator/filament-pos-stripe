<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasDashboardDateRange;
use App\Models\ConnectedCharge;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class TopProductsWidget extends ChartWidget
{
    use HasDashboardDateRange;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'sm' => 'full',
        'md' => 'full',
        'lg' => 4,
        'xl' => 4,
    ];

    public function getHeading(): string
    {
        return 'Top Products';
    }

    /**
     * Parse item amount to øre.
     */
    protected function parseAmountToOre(mixed $value): int
    {
        if (is_string($value)) {
            $hasFormatting = str_contains($value, ',')
                || str_contains($value, ' ')
                || (str_contains($value, '.') && preg_match('/\.\d{2}$/', $value));

            if ($hasFormatting) {
                $cleaned = str_replace([' ', ','], ['', '.'], $value);
                $cleaned = preg_replace('/[^\d.]/', '', $cleaned);
                if ($cleaned === '' || $cleaned === null) {
                    return 0;
                }

                return (int) round((float) $cleaned * 100);
            }

            return (int) round((float) $value);
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return 0;
    }

    /**
     * Resolve total cart discounts in øre.
     *
     * @param  array<string, mixed>  $metadata
     */
    protected function resolveTotalDiscountsOre(array $metadata): int
    {
        if (isset($metadata['total_discounts']) && is_numeric($metadata['total_discounts'])) {
            return max(0, (int) $metadata['total_discounts']);
        }

        $discounts = $metadata['discounts'] ?? [];
        if (! is_array($discounts)) {
            return 0;
        }

        $sum = 0;
        foreach ($discounts as $discount) {
            if (! is_array($discount)) {
                continue;
            }
            $sum += max(0, $this->parseAmountToOre($discount['amount'] ?? 0));
        }

        return $sum;
    }

    protected function getData(): array
    {
        $store = Filament::getTenant();

        if (! $store) {
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
            $lineEntries = [];
            $itemDiscountsTotalOre = 0;
            $netBeforeCartDiscountTotalOre = 0;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 1.0;
                $unitPriceOre = (int) ($item['unit_price'] ?? $item['price'] ?? 0);
                $lineTotalOre = (int) round($unitPriceOre * $quantity);

                if (isset($item['line_total_amount']) && is_numeric($item['line_total_amount'])) {
                    $lineTotalOre = (int) round((float) $item['line_total_amount']);
                } elseif (isset($item['line_total']) && is_numeric($item['line_total'])) {
                    $lineTotalOre = (int) round((float) $item['line_total']);
                }

                $lineTotalOre = max(0, $lineTotalOre);

                $itemDiscountOre = 0;
                if (isset($item['discount_total_amount']) && is_numeric($item['discount_total_amount'])) {
                    $itemDiscountOre = max(0, (int) round((float) $item['discount_total_amount']));
                } elseif (isset($item['discount_amount'])) {
                    $discountPerUnitOre = $this->parseAmountToOre($item['discount_amount']);
                    $itemDiscountOre = (int) round($discountPerUnitOre * $quantity);
                }

                $itemDiscountOre = min($itemDiscountOre, $lineTotalOre);
                $lineNetBeforeCartDiscountOre = max(0, $lineTotalOre - $itemDiscountOre);

                $lineEntries[] = [
                    'item' => $item,
                    'quantity' => $quantity,
                    'line_net_ore' => $lineNetBeforeCartDiscountOre,
                ];

                $itemDiscountsTotalOre += $itemDiscountOre;
                $netBeforeCartDiscountTotalOre += $lineNetBeforeCartDiscountOre;
            }

            $totalDiscountsOre = $this->resolveTotalDiscountsOre(is_array($metadata) ? $metadata : []);
            $remainingCartDiscountOre = max(0, $totalDiscountsOre - $itemDiscountsTotalOre);

            if ($remainingCartDiscountOre > 0 && $netBeforeCartDiscountTotalOre > 0) {
                $lastPositiveIndex = null;
                foreach ($lineEntries as $index => $entry) {
                    if ($entry['line_net_ore'] > 0) {
                        $lastPositiveIndex = $index;
                    }
                }

                $allocated = 0;

                foreach ($lineEntries as $index => &$entry) {
                    $base = $entry['line_net_ore'];
                    if ($base <= 0) {
                        continue;
                    }

                    if ($index === $lastPositiveIndex) {
                        $cartShare = $remainingCartDiscountOre - $allocated;
                    } else {
                        $cartShare = (int) floor(($remainingCartDiscountOre * $base) / $netBeforeCartDiscountTotalOre);
                        $allocated += $cartShare;
                    }

                    $entry['line_net_ore'] = max(0, $base - $cartShare);
                }
                unset($entry);
            }

            $lineNetTotalOre = array_sum(array_column($lineEntries, 'line_net_ore'));
            $chargeAmountOre = max(0, (int) $charge->amount - (int) ($charge->tip_amount ?? 0));
            $allocationRatio = $lineNetTotalOre > 0
                ? min(1, $chargeAmountOre / $lineNetTotalOre)
                : 1.0;

            foreach ($lineEntries as $entry) {
                $item = $entry['item'];
                $productId = $item['product_id'] ?? $item['stripe_product_id'] ?? null;
                $productName = $item['name'] ?? $item['product_name'] ?? 'Unknown Product';
                $quantity = $entry['quantity'];
                $paidLineTotalOre = $entry['line_net_ore'] * $allocationRatio;

                if ($productId || $productName !== 'Unknown Product') {
                    $key = $productId ?: $productName;

                    if (! isset($productStats[$key])) {
                        $productStats[$key] = [
                            'name' => $productName,
                            'quantity' => 0,
                            'revenue_ore' => 0.0,
                            'transactions' => 0,
                        ];
                    }

                    $productStats[$key]['quantity'] += $quantity;
                    $productStats[$key]['revenue_ore'] += $paidLineTotalOre;
                    $productStats[$key]['transactions'] += 1;
                }
            }
        }

        // Sort by revenue and take top 10
        usort($productStats, function ($a, $b) {
            return $b['revenue_ore'] <=> $a['revenue_ore'];
        });

        $topProducts = array_slice($productStats, 0, 10);

        if (empty($topProducts)) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Prepare data for chart
        $labels = array_map(fn ($product) => $product['name'], $topProducts);
        $revenues = array_map(fn ($product) => round($product['revenue_ore'] / 100, 2), $topProducts);
        $quantities = array_map(fn ($product) => $product['quantity'], $topProducts);

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
