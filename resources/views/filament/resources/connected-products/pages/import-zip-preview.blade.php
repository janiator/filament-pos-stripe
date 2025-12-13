@php
    $previewData = $this->getPreviewData();
@endphp

@if($previewData)
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Collections</div>
                <div class="text-2xl font-bold">{{ $previewData['collections_count'] ?? 0 }}</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Products</div>
                <div class="text-2xl font-bold">{{ $previewData['products_count'] ?? 0 }}</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Relations</div>
                <div class="text-2xl font-bold">{{ $previewData['relations_count'] ?? 0 }}</div>
            </div>
        </div>

        @if(!empty($previewData['export_date']))
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Export date: {{ \Carbon\Carbon::parse($previewData['export_date'])->format('Y-m-d H:i:s') }}
            </div>
        @endif

        @if(!empty($previewData['collections']))
            <div>
                <h3 class="text-lg font-semibold mb-2">Sample Collections (first 10)</h3>
                <div class="max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left">Name</th>
                                <th class="px-4 py-2 text-left">Handle</th>
                                <th class="px-4 py-2 text-left">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($previewData['collections'] as $collection)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-2 font-medium">{{ $collection['name'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $collection['handle'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-2">
                                        @if($collection['active'] ?? true)
                                            <span class="text-green-600 dark:text-green-400">Yes</span>
                                        @else
                                            <span class="text-gray-400">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!empty($previewData['products']))
            <div>
                <h3 class="text-lg font-semibold mb-2">Sample Products (first 10)</h3>
                <div class="max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left">Name</th>
                                <th class="px-4 py-2 text-left">Price</th>
                                <th class="px-4 py-2 text-left">Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($previewData['products'] as $product)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-2 font-medium">{{ $product['name'] ?? 'N/A' }}</td>
                                    <td class="px-4 py-2">
                                        @if(isset($product['price']))
                                            {{ number_format((float) $product['price'], 2, ',', ' ') }} {{ $product['currency'] ?? 'NOK' }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($product['active'] ?? true)
                                            <span class="text-green-600 dark:text-green-400">Yes</span>
                                        @else
                                            <span class="text-gray-400">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@else
    <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
        No preview data available. Please upload a ZIP file in the previous step.
    </div>
@endif
