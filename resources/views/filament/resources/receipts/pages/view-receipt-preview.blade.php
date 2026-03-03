<x-filament-panels::page>
    <style>
        .receipt-preview-paper {
            max-width: 320px;
            margin: 0 auto;
            padding: 1.5rem;
            font-family: ui-monospace, monospace;
            font-size: 0.875rem;
            line-height: 1.35;
            background: #fff;
            color: #111;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        .dark .receipt-preview-paper {
            background: #f9fafb;
            color: #111;
            border-color: #d1d5db;
        }
        .receipt-preview-paper .receipt-line {
            word-break: break-word;
        }
        .receipt-preview-paper .receipt-image-placeholder {
            text-align: center;
            padding: 0.5rem 0;
            color: #6b7280;
            font-size: 0.75rem;
        }
        .receipt-preview-paper .receipt-barcode {
            letter-spacing: 0.15em;
            font-weight: 600;
        }
    </style>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Receipt Preview</h2>
                <a
                    href="{{ route('receipts.xml.simple', ['id' => $record->id]) }}"
                    target="_blank"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    Download XML
                </a>
            </div>

            <div class="flex justify-center py-6 bg-gray-100 dark:bg-gray-900 rounded-lg">
                {!! app(\App\Services\ReceiptTemplateService::class)->renderReceiptAsHtml($record) !!}
            </div>

            <details class="mt-4">
                <summary class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">View XML source</summary>
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-gray-900 text-gray-100 mt-2">
                    <pre class="p-4 text-xs font-mono overflow-x-auto overflow-y-auto max-h-[400px] whitespace-pre select-text" style="tab-size: 4;">{{ htmlspecialchars($this->getFormattedReceiptXml()) }}</pre>
                </div>
            </details>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-md font-semibold mb-4">Receipt Information</h3>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Receipt Number</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->receipt_number }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Receipt Type</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($record->receipt_type) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Store</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->store->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->created_at->format('Y-m-d H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Printed</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($record->printed)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Yes ({{ $record->printed_at?->format('Y-m-d H:i:s') }})
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                No
                            </span>
                        @endif
                    </dd>
                </div>
                @if($record->reprint_count > 0)
                <div>
                    <dt class="text-sm font-medium text-gray-500">Reprint Count</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $record->reprint_count }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>
</x-filament-panels::page>

