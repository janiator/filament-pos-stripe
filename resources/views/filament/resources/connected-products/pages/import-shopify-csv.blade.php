<x-filament-panels::page>
    <form wire:submit="importProducts">
        {{ $this->form }}

        @php
            $previewData = $this->getPreviewData();
        @endphp

        @if($previewData && isset($previewData['products']))
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    Preview
                </x-slot>
                <x-slot name="description">
                    Review the products that will be imported
                </x-slot>

                <div class="space-y-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>{{ $previewData['total_products'] ?? 0 }}</strong> products with 
                        <strong>{{ $previewData['total_variants'] ?? 0 }}</strong> variants will be imported.
                    </div>

                    <div class="max-h-96 overflow-y-auto">
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
                                                $prices = array_filter(array_map(fn($v) => !empty($v['price']) ? (float)$v['price'] : null, $variants));
                                                if (!empty($prices)) {
                                                    $minPrice = min($prices);
                                                    $maxPrice = max($prices);
                                                    echo $minPrice === $maxPrice ? number_format($minPrice, 2) . ' NOK' : number_format($minPrice, 2) . ' - ' . number_format($maxPrice, 2) . ' NOK';
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
                            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400 text-center">
                                ... and {{ count($previewData['products']) - 20 }} more products
                            </div>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endif
    </form>
</x-filament-panels::page>

