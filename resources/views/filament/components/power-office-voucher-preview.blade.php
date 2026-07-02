@php
    /** @var array<string, mixed> $preview */
    $ok = (bool) ($preview['ok'] ?? false);
    $balanced = (bool) ($preview['balanced'] ?? false);
    $currency = (string) ($preview['currency'] ?? 'NOK');
    $lines = is_array($preview['lines_display'] ?? null) ? $preview['lines_display'] : [];
    $debitTotal = round(((int) ($preview['debit_total_minor'] ?? 0)) / 100, 2);
    $creditTotal = round(((int) ($preview['credit_total_minor'] ?? 0)) / 100, 2);
    $difference = round(((int) ($preview['difference_minor'] ?? 0)) / 100, 2);
    $formatMoney = static fn (float $amount): string => number_format($amount, 2, ',', ' ');
@endphp

@if(! $ok)
    <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
        {{ $preview['error'] ?? __('Could not build voucher preview.') }}
        @if(filled($preview['missing_basis_keys'] ?? null) && is_array($preview['missing_basis_keys']))
            <ul class="mt-2 list-disc pl-5">
                @foreach($preview['missing_basis_keys'] as $key)
                    <li>{{ $key }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@else
    <div @class([
        'rounded-lg border px-4 py-3 text-sm',
        'border-success-300 bg-success-50 text-success-800 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-200' => $balanced,
        'border-danger-300 bg-danger-50 text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-200' => ! $balanced,
    ])>
        @if($balanced)
            {{ __('Voucher is in balance.') }}
        @else
            {{ __('Voucher is not in balance — PowerOffice will reject this posting.') }}
        @endif
        <span class="mt-1 block font-mono text-xs opacity-90">
            {{ __('Debit') }}: {{ $formatMoney($debitTotal) }} {{ $currency }}
            · {{ __('Credit') }}: {{ $formatMoney($creditTotal) }} {{ $currency }}
            · {{ __('Difference') }}: {{ $formatMoney($difference) }} {{ $currency }}
        </span>
    </div>

    @if(filled($preview['description'] ?? null) || filled($preview['document_date'] ?? null))
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            @if(filled($preview['document_date'] ?? null))
                <span class="font-medium">{{ $preview['document_date'] }}</span>
            @endif
            @if(filled($preview['description'] ?? null))
                — {{ $preview['description'] }}
            @endif
            @if(filled($preview['department_no'] ?? null))
                · {{ __('Dept.') }} {{ $preview['department_no'] }}
            @endif
        </p>
    @endif

    @if($lines === [])
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('No voucher lines.') }}</p>
    @else
        <div class="mt-4 max-h-80 overflow-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Account') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">{{ __('Description') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">{{ __('Debit') }}</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">{{ __('Credit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($lines as $row)
                        <tr wire:key="po-voucher-line-{{ $loop->index }}-{{ $row['account'] ?? '' }}">
                            <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">{{ $row['account'] ?? '' }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['description'] ?? '' }}</td>
                            <td class="px-3 py-2 text-right font-mono text-gray-900 dark:text-white">
                                @if(($row['debit'] ?? 0) > 0)
                                    {{ $formatMoney((float) $row['debit']) }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-gray-900 dark:text-white">
                                @if(($row['credit'] ?? 0) > 0)
                                    {{ $formatMoney((float) $row['credit']) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <td colspan="2" class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ __('Total') }}</td>
                        <td class="px-3 py-2 text-right font-mono font-medium text-gray-900 dark:text-white">{{ $formatMoney($debitTotal) }}</td>
                        <td class="px-3 py-2 text-right font-mono font-medium text-gray-900 dark:text-white">{{ $formatMoney($creditTotal) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    @if(filled($preview['resolve_error'] ?? null))
        <p class="mt-3 text-sm text-warning-600 dark:text-warning-400">{{ $preview['resolve_error'] }}</p>
    @endif
@endif
