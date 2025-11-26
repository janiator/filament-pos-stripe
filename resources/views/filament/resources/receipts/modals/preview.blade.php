<div class="space-y-4">
    <!-- Receipt Info -->
    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-semibold">Receipt Number:</span>
                <span>{{ $receipt->receipt_number }}</span>
            </div>
            <div>
                <span class="font-semibold">Type:</span>
                <span class="capitalize">{{ $receipt->receipt_type }}</span>
            </div>
            <div>
                <span class="font-semibold">Store:</span>
                <span>{{ $receipt->store->name ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="font-semibold">Created:</span>
                <span>{{ $receipt->created_at->format('Y-m-d H:i:s') }}</span>
            </div>
        </div>
    </div>

    <!-- Receipt Data Preview -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">Receipt Data</h3>
        <div class="space-y-2 text-sm">
            @if(isset($receipt->receipt_data['store']))
                <div>
                    <span class="font-semibold">Store:</span>
                    <span>{{ $receipt->receipt_data['store']['name'] ?? 'N/A' }}</span>
                </div>
            @endif
            @if(isset($receipt->receipt_data['date']))
                <div>
                    <span class="font-semibold">Date:</span>
                    <span>{{ $receipt->receipt_data['date'] }}</span>
                </div>
            @endif
            @if(isset($receipt->receipt_data['transaction_id']))
                <div>
                    <span class="font-semibold">Transaction ID:</span>
                    <span>{{ $receipt->receipt_data['transaction_id'] }}</span>
                </div>
            @endif
            @if(isset($receipt->receipt_data['cashier']))
                <div>
                    <span class="font-semibold">Cashier:</span>
                    <span>{{ $receipt->receipt_data['cashier'] }}</span>
                </div>
            @endif
        </div>

        <!-- Items -->
        @if(isset($receipt->receipt_data['items']) && is_array($receipt->receipt_data['items']))
            <div class="mt-4">
                <h4 class="font-semibold mb-2">Items:</h4>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-2">
                    @foreach($receipt->receipt_data['items'] as $item)
                        <div class="flex justify-between py-1">
                            <div>
                                <span class="font-medium">{{ $item['name'] ?? 'Item' }}</span>
                                @if(isset($item['quantity']))
                                    <span class="text-gray-500">x{{ $item['quantity'] }}</span>
                                @endif
                            </div>
                            <div class="font-semibold">
                                {{ $item['line_total'] ?? $item['unit_price'] ?? 'N/A' }} NOK
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Totals -->
        @if(isset($receipt->receipt_data['total']))
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between text-lg font-bold">
                    <span>Total:</span>
                    <span>{{ number_format($receipt->receipt_data['total'], 2, ',', ' ') }} NOK</span>
                </div>
                @if(isset($receipt->receipt_data['tax']))
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        VAT: {{ number_format($receipt->receipt_data['tax'], 2, ',', ' ') }} NOK
                    </div>
                @endif
            </div>
        @endif

        <!-- Payment Method -->
        @if(isset($receipt->receipt_data['payment_method']))
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <span class="font-semibold">Payment Method:</span>
                    <span class="capitalize">{{ $receipt->receipt_data['payment_method'] }}</span>
                </div>
            </div>
        @endif
    </div>

    <!-- Receipt Visual Preview -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-2">Receipt Visual Preview</h3>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden" style="height: 600px;">
            <iframe 
                id="receipt-preview-iframe"
                src="/epson-editor/preview-only.html?xml={{ rawurlencode(base64_encode(app(\App\Services\ReceiptTemplateService::class)->renderReceipt($receipt))) }}"
                style="width: 100%; height: 100%; border: none;"
                title="Receipt Preview"
            ></iframe>
        </div>
    </div>

    <!-- XML Preview (Collapsible) -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <details class="cursor-pointer">
            <summary class="text-lg font-semibold mb-2">XML Output</summary>
            <div class="bg-gray-900 text-gray-100 p-4 rounded overflow-x-auto text-xs font-mono max-h-96 overflow-y-auto mt-2">
                <pre>{{ htmlspecialchars(app(\App\Services\ReceiptTemplateService::class)->renderReceipt($receipt)) }}</pre>
            </div>
        </details>
    </div>

    <!-- Actions -->
    <div class="flex gap-2">
        <a 
            href="/app/store/{{ \Filament\Facades\Filament::getTenant()->slug }}/receipts/{{ $receipt->id }}/xml"
            target="_blank"
            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Download XML
        </a>
    </div>
</div>

