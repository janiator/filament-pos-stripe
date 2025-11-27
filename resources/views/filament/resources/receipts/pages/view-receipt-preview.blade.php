<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Receipt Preview</h2>
                <div class="flex gap-2">
                    <a 
                        href="{{ route('receipts.xml.simple', ['id' => $record->id]) }}" 
                        target="_blank"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Download XML
                    </a>
                    <a 
                        href="{{ $this->getPreviewUrl() }}" 
                        target="_blank"
                        class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        Open in New Tab
                    </a>
                </div>
            </div>
            
            <div class="border rounded-lg overflow-hidden" style="min-height: 600px;">
                <iframe 
                    src="{{ $this->getPreviewUrl() }}" 
                    class="w-full h-full border-0"
                    style="min-height: 600px;"
                    title="Receipt Preview"
                ></iframe>
            </div>
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

