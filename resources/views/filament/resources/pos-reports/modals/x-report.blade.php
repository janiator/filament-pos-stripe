<div class="space-y-4">
    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
        <h3 class="text-lg font-semibold mb-2">X-Report (Interim Report)</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-gray-600 dark:text-gray-300">
            <div><strong>Session:</strong> {{ $session->session_number }}</div>
            <div><strong>Store:</strong> {{ $report['store']['name'] ?? 'N/A' }}</div>
            <div><strong>Opened:</strong> {{ $session->opened_at->format('d.m.Y H:i') }}</div>
            <div><strong>Generated:</strong> {{ is_string($report['report_generated_at']) ? \Carbon\Carbon::parse($report['report_generated_at'])->format('d.m.Y H:i') : $report['report_generated_at']->format('d.m.Y H:i') }}</div>
            @if($report['device'])
                <div><strong>Device:</strong> {{ $report['device']['name'] }}</div>
            @endif
            @if($report['cashier'])
                <div><strong>Cashier:</strong> {{ $report['cashier']['name'] }}</div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Transactions</div>
            <div class="text-2xl font-bold">{{ $report['transactions_count'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Total Amount</div>
            <div class="text-2xl font-bold">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Cash</div>
            <div class="text-2xl font-bold">{{ number_format($report['cash_amount'] / 100, 2) }} NOK</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
            <div class="text-sm text-gray-500 dark:text-gray-400">Card</div>
            <div class="text-2xl font-bold">{{ number_format($report['card_amount'] / 100, 2) }} NOK</div>
        </div>
    </div>

    @if($report['mobile_amount'] > 0 || $report['other_amount'] > 0)
        <div class="grid grid-cols-2 gap-4">
            @if($report['mobile_amount'] > 0)
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Mobile</div>
                    <div class="text-xl font-bold">{{ number_format($report['mobile_amount'] / 100, 2) }} NOK</div>
                </div>
            @endif
            @if($report['other_amount'] > 0)
                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Other</div>
                    <div class="text-xl font-bold">{{ number_format($report['other_amount'] / 100, 2) }} NOK</div>
                </div>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Opening Balance:</strong><br>
                {{ number_format(($report['opening_balance'] ?? 0) / 100, 2) }} NOK
            </div>
        </div>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Expected Cash:</strong><br>
                {{ number_format($report['expected_cash'] / 100, 2) }} NOK
            </div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Total Tips:</strong><br>
                {{ number_format(($report['total_tips'] ?? 0) / 100, 2) }} NOK
            </div>
        </div>
    </div>

    <!-- VAT Breakdown -->
    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
        <h4 class="font-semibold mb-2">VAT Breakdown</h4>
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-gray-500 dark:text-gray-400">VAT Base</div>
                <div class="font-semibold">{{ number_format(($report['vat_base'] ?? 0) / 100, 2) }} NOK</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">VAT Amount ({{ $report['vat_rate'] ?? 25 }}%)</div>
                <div class="font-semibold">{{ number_format(($report['vat_amount'] ?? 0) / 100, 2) }} NOK</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">Total (incl. VAT)</div>
                <div class="font-semibold">{{ number_format($report['total_amount'] / 100, 2) }} NOK</div>
            </div>
        </div>
    </div>

    <!-- Cash Drawer & Receipts -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Cash Drawer Opens:</strong><br>
                {{ $report['cash_drawer_opens'] ?? 0 }}
            </div>
        </div>
        <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Nullinnslag Count:</strong><br>
                {{ $report['nullinnslag_count'] ?? 0 }}
            </div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Receipts Generated:</strong><br>
                {{ $report['receipt_count'] ?? 0 }}
            </div>
        </div>
    </div>

    <!-- Payment Code Breakdown -->
    @if(isset($report['by_payment_code']) && $report['by_payment_code']->count() > 0)
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <h4 class="font-semibold mb-2">Payment Code Breakdown</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left p-2">Payment Code</th>
                            <th class="text-left p-2">Count</th>
                            <th class="text-right p-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['by_payment_code'] as $code => $data)
                            <tr class="border-b">
                                <td class="p-2">{{ $code }}</td>
                                <td class="p-2">{{ $data['count'] }}</td>
                                <td class="p-2 text-right">{{ number_format($data['amount'] / 100, 2) }} NOK</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($report['charges']->count() > 0)
        <div>
            <h4 class="font-semibold mb-2">Recent Transactions</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left p-2">Time</th>
                            <th class="text-left p-2">Method</th>
                            <th class="text-right p-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['charges']->take(10) as $charge)
                            <tr class="border-b">
                                <td class="p-2">{{ $charge->paid_at?->format('H:i') ?? $charge->created_at->format('H:i') }}</td>
                                <td class="p-2 capitalize">{{ $charge->payment_method }}</td>
                                <td class="p-2 text-right">{{ number_format($charge->amount / 100, 2) }} NOK</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

