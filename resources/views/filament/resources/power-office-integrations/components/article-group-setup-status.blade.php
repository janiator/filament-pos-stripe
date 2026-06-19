@php
    /** @var list<array{code: string, name: string, sales_account: ?string, product_count: int, mapped: bool}> $rows */
    /** @var string|null $articleGroupCodesUrl */
@endphp

<p class="text-sm text-gray-600 dark:text-gray-400">
    {{ __('Assign each product’s article group code in the product editor. Mappings here decide which PowerOffice sales account receives turnover when no vendor commission applies.') }}
    @if(filled($articleGroupCodesUrl))
        <a href="{{ $articleGroupCodesUrl }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ __('Manage article group codes') }}</a>
    @endif
</p>

@if($rows === [])
    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('No article group codes yet. Create codes (e.g. Neverstua, Vaskeri, Kantine) before mapping accounts.') }}</p>
@else
    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Code') }}</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Name') }}</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Sales account') }}</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">{{ __('Products') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach($rows as $row)
                    <tr wire:key="article-group-setup-{{ $row['code'] }}">
                        <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $row['code'] }}</td>
                        <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                        <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                            @if($row['mapped'])
                                {{ $row['sales_account'] }}
                            @else
                                <span class="text-amber-600 dark:text-amber-400">{{ __('Not mapped') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $row['product_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
