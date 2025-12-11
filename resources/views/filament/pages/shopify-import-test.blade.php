<x-filament::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
            {{ $this->form }}
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button wire:click="parseCsv" icon="heroicon-o-magnifying-glass">
                    Parse CSV
                </x-filament::button>

                <x-filament::button wire:click="runImport" color="success" icon="heroicon-o-arrow-up-tray">
                    Run Import
                </x-filament::button>
            </div>
        </div>

        @if ($parseResult)
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
                <div class="text-sm font-semibold mb-3">Parse Stats</div>

                @php $s = $parseResult['stats'] ?? []; @endphp

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Products</div>
                        <div class="text-2xl font-semibold">{{ $s['total_products'] ?? ($parseResult['total_products'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Variants</div>
                        <div class="text-2xl font-semibold">{{ $s['total_variants'] ?? ($parseResult['total_variants'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Variable products</div>
                        <div class="text-2xl font-semibold">{{ $s['variable_products'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Single-like products</div>
                        <div class="text-2xl font-semibold">{{ $s['single_like_products'] ?? 0 }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Vendors</div>
                        <div class="text-2xl font-semibold">{{ $s['unique_vendors'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Types</div>
                        <div class="text-2xl font-semibold">{{ $s['unique_types'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Categories</div>
                        <div class="text-2xl font-semibold">{{ $s['unique_categories'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Tags</div>
                        <div class="text-2xl font-semibold">{{ $s['unique_tags'] ?? 0 }}</div>
                    </div>

                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Images</div>
                        <div class="text-2xl font-semibold">{{ $s['total_images'] ?? 0 }}</div>
                    </div>
                </div>

                <details class="mt-4">
                    <summary class="cursor-pointer text-sm text-gray-600 dark:text-gray-300">
                        Raw parse payload
                    </summary>
                    <pre class="mt-2 text-xs whitespace-pre-wrap bg-gray-50 dark:bg-gray-950 p-3 rounded-lg overflow-auto">
{{ json_encode($parseResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                    </pre>
                </details>
            </div>
        @endif

        @if ($importResult)
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
                <div class="text-sm font-semibold mb-3">Import Stats</div>

                @php
                    $istats = $importResult['stats']['import'] ?? [];
                    $errCount = (int) ($importResult['error_count'] ?? ($istats['error_count'] ?? 0));
                @endphp

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Imported</div>
                        <div class="text-2xl font-semibold">{{ $istats['imported'] ?? ($importResult['imported'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Skipped</div>
                        <div class="text-2xl font-semibold">{{ $istats['skipped'] ?? ($importResult['skipped'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Errors</div>
                        <div class="text-2xl font-semibold">{{ $errCount }}</div>
                    </div>
                    <div class="rounded-lg border p-3">
                        <div class="text-xs text-gray-500">Total products</div>
                        <div class="text-2xl font-semibold">{{ $istats['total_products'] ?? 0 }}</div>
                    </div>
                </div>

                @if (!empty($importResult['errors']))
                    <div class="mt-4">
                        <div class="text-xs font-semibold text-red-600 dark:text-red-400 mb-2">
                            First errors
                        </div>
                        <ul class="text-xs list-disc pl-5 space-y-1">
                            @foreach (array_slice($importResult['errors'], 0, 20) as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <details class="mt-4">
                    <summary class="cursor-pointer text-sm text-gray-600 dark:text-gray-300">
                        Raw import payload
                    </summary>
                    <pre class="mt-2 text-xs whitespace-pre-wrap bg-gray-50 dark:bg-gray-950 p-3 rounded-lg overflow-auto">
{{ json_encode($importResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                    </pre>
                </details>
            </div>
        @endif
    </div>
</x-filament::page>
