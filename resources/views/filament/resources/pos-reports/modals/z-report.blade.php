<div class="space-y-4">
    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
        <h3 class="text-lg font-semibold mb-2">Z-Report (End-of-Day Report)</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-gray-600 dark:text-gray-300">
            <div><strong>Session:</strong> {{ $session->session_number }}</div>
            <div><strong>Store:</strong> {{ $report['store']['name'] ?? 'N/A' }}</div>
            <div><strong>Opened:</strong> {{ $session->opened_at->format('d.m.Y H:i') }}</div>
            <div><strong>Closed:</strong> {{ $session->closed_at?->format('d.m.Y H:i') ?? 'N/A' }}</div>
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

    <!-- Opening Balance & Cash Summary -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Opening Balance:</strong><br>
                {{ number_format(($report['opening_balance'] ?? 0) / 100, 2) }} NOK
            </div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Expected Cash:</strong><br>
                {{ number_format($report['expected_cash'] / 100, 2) }} NOK
            </div>
        </div>
        <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Actual Cash:</strong><br>
                {{ number_format(($report['actual_cash'] ?? 0) / 100, 2) }} NOK
            </div>
        </div>
        <div class="bg-{{ ($report['cash_difference'] ?? 0) > 0 ? 'red' : (($report['cash_difference'] ?? 0) < 0 ? 'yellow' : 'green') }}-50 dark:bg-{{ ($report['cash_difference'] ?? 0) > 0 ? 'red' : (($report['cash_difference'] ?? 0) < 0 ? 'yellow' : 'green') }}-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Difference:</strong><br>
                {{ number_format(($report['cash_difference'] ?? 0) / 100, 2) }} NOK
            </div>
        </div>
        @if($report['tips_enabled'] ?? true)
            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <strong>Total Tips:</strong><br>
                    {{ number_format(($report['total_tips'] ?? 0) / 100, 2) }} NOK
                </div>
            </div>
        @endif
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

    <!-- Cash Drawer, Receipts & Events -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Cash Drawer Opens:</strong><br>
                {{ $report['cash_drawer_opens'] ?? 0 }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Nullinnslag: {{ $report['nullinnslag_count'] ?? 0 }}
            </div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Receipts Generated:</strong><br>
                {{ $report['receipt_count'] ?? 0 }}
            </div>
            @if(isset($report['receipt_summary']) && $report['receipt_summary']->count() > 0)
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    @foreach($report['receipt_summary'] as $type => $data)
                        {{ ucfirst($type) }}: {{ $data['count'] }}@if(!$loop->last), @endif
                    @endforeach
                </div>
            @endif
        </div>
        <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <strong>Total Events:</strong><br>
                {{ isset($report['event_summary']) ? $report['event_summary']->sum('count') : 0 }}
            </div>
        </div>
    </div>

    @if(!empty($report['closing_notes']))
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <div class="text-sm font-semibold mb-1">Closing Notes:</div>
            <div class="text-sm text-gray-600 dark:text-gray-300">{{ $report['closing_notes'] }}</div>
        </div>
    @endif

    <!-- Event Summary -->
    @if(isset($report['event_summary']) && $report['event_summary']->count() > 0)
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <h4 class="font-semibold mb-2">Event Summary</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left p-2">Event Code</th>
                            <th class="text-left p-2">Description</th>
                            <th class="text-right p-2">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['event_summary'] as $event)
                            <tr class="border-b">
                                <td class="p-2">{{ $event['code'] }}</td>
                                <td class="p-2">{{ $event['description'] }}</td>
                                <td class="p-2 text-right">{{ $event['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Complete Transaction List -->
    @if(isset($report['complete_transaction_list']) && count($report['complete_transaction_list']) > 0)
        <div>
            <h4 class="font-semibold mb-2">Complete Transaction List ({{ count($report['complete_transaction_list']) }} transactions)</h4>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-gray-100 dark:bg-gray-800">
                        <tr class="border-b">
                            <th class="text-left p-2">Time</th>
                            <th class="text-left p-2">ID</th>
                            <th class="text-left p-2">Method</th>
                            <th class="text-left p-2">Payment Code</th>
                            <th class="text-left p-2">Transaction Code</th>
                            <th class="text-right p-2">Amount</th>
                            @if($report['tips_enabled'] ?? true)
                                <th class="text-right p-2">Tip</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['complete_transaction_list'] as $transaction)
                            <tr class="border-b">
                                <td class="p-2">{{ \Carbon\Carbon::parse($transaction['paid_at'] ?? $transaction['created_at'])->format('H:i:s') }}</td>
                                <td class="p-2 text-xs">{{ substr($transaction['stripe_charge_id'] ?? $transaction['id'], 0, 12) }}...</td>
                                <td class="p-2 capitalize">{{ $transaction['payment_method'] ?? 'N/A' }}</td>
                                <td class="p-2">{{ $transaction['payment_code'] ?? 'N/A' }}</td>
                                <td class="p-2">{{ $transaction['transaction_code'] ?? 'N/A' }}</td>
                                <td class="p-2 text-right">{{ number_format($transaction['amount'] / 100, 2) }} NOK</td>
                                @if($report['tips_enabled'] ?? true)
                                    <td class="p-2 text-right">{{ ($transaction['tip_amount'] ?? 0) > 0 ? number_format($transaction['tip_amount'] / 100, 2) . ' NOK' : '-' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($report['charges']->count() > 0)
        <div>
            <h4 class="font-semibold mb-2">All Transactions</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left p-2">Time</th>
                            <th class="text-left p-2">Method</th>
                            <th class="text-left p-2">Code</th>
                            <th class="text-right p-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['charges'] as $charge)
                            <tr class="border-b">
                                <td class="p-2">{{ $charge->paid_at?->format('H:i') ?? $charge->created_at->format('H:i') }}</td>
                                <td class="p-2 capitalize">{{ $charge->payment_method }}</td>
                                <td class="p-2">{{ $charge->payment_code ?? 'N/A' }}</td>
                                <td class="p-2 text-right">{{ number_format($charge->amount / 100, 2) }} NOK</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

