@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Vendor> $vendors */
    /** @var string|null $vendorsUrl */
@endphp

<p class="text-sm text-gray-600 dark:text-gray-400">
    {{ __('Vendor reskontro and commission are configured on each vendor — not here. This integration uses those fields when posting Z-report sales.') }}
    @if(filled($vendorsUrl))
        <a href="{{ $vendorsUrl }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ __('Open vendors') }}</a>
    @endif
</p>

@if($vendors->isEmpty())
    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('No vendors yet.') }}</p>
@else
    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Vendor') }}</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Reskontro') }}</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Commission') }}</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Commission account') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach($vendors as $vendor)
                    <tr wire:key="vendor-ledger-{{ $vendor->getKey() }}">
                        <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $vendor->name }}</td>
                        <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                            {{ $vendor->supplier_ledger_account_number ?: '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                            @if(filled($vendor->commission_percent))
                                {{ $vendor->commission_percent }}%
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-300">
                            {{ $vendor->commission_revenue_account_number ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
