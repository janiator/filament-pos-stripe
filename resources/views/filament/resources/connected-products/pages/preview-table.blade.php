@php
    $previewData = $this->getPreviewData();
@endphp

@if($previewData && isset($previewData['products']))
    <div class="space-y-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            <strong>{{ $previewData['total_products'] ?? 0 }}</strong> products with 
            <strong>{{ $previewData['total_variants'] ?? 0 }}</strong> variants will be imported.
        </div>

        <div class="max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left">Product</th>
                        <th class="px-4 py-2 text-left">Variants</th>
                        <th class="px-4 py-2 text-left">Price Range</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach(array_slice($previewData['products'], 0, 20) as $product)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-2">
                                <div class="font-medium">{{ $product['title'] ?? 'N/A' }}</div>
                                @if(!empty($product['vendor']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $product['vendor'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                {{ count($product['variants'] ?? []) }} variant(s)
                            </td>
                            <td class="px-4 py-2">
                                @php
                                    $variants = $product['variants'] ?? [];
                                    // Parse prices from variant data - price is stored as string like "5999.00"
                                    $prices = [];
                                    foreach ($variants as $variant) {
                                        if (!empty($variant['price'])) {
                                            // Price is stored as string, convert to float
                                            $price = is_numeric($variant['price']) ? (float)$variant['price'] : null;
                                            if ($price !== null && $price > 0) {
                                                $prices[] = $price;
                                            }
                                        }
                                    }
                                    
                                    if (!empty($prices)) {
                                        $minPrice = min($prices);
                                        $maxPrice = max($prices);
                                        // Format with thousands separator
                                        $formattedMin = number_format($minPrice, 2, ',', ' ');
                                        $formattedMax = number_format($maxPrice, 2, ',', ' ');
                                        echo $minPrice === $maxPrice ? $formattedMin . ' NOK' : $formattedMin . ' - ' . $formattedMax . ' NOK';
                                    } else {
                                        echo 'N/A';
                                    }
                                @endphp
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if(count($previewData['products']) > 20)
                <div class="mt-2 p-4 text-sm text-gray-500 dark:text-gray-400 text-center bg-gray-50 dark:bg-gray-800">
                    ... and {{ count($previewData['products']) - 20 }} more products
                </div>
            @endif
        </div>
    </div>
@else
    <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
        No preview data available. Please upload a CSV file in the previous step.
    </div>
@endif

