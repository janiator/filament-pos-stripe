<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filters -->
        <x-filament::section>
            <x-slot name="heading">
                Filters
            </x-slot>
            <x-slot name="description">
                Select date range and session to filter reports
            </x-slot>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                    <input
                        type="date"
                        wire:model.live="fromDate"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                    <input
                        type="date"
                        wire:model.live="toDate"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                </div>
            </div>
        </x-filament::section>

        <!-- Sales Overview -->
        <x-filament::section>
            <x-slot name="heading">
                Sales Overview
            </x-slot>
            <x-slot name="description">
                Summary statistics for the selected period
            </x-slot>
            
            @php
                $overview = $this->getSalesOverview();
            @endphp
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Sessions</div>
                    <div class="text-2xl font-bold">{{ $overview['totals']['sessions'] }}</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Transactions</div>
                    <div class="text-2xl font-bold">{{ number_format($overview['totals']['transactions']) }}</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Amount</div>
                    <div class="text-2xl font-bold">{{ number_format($overview['totals']['amount'] / 100, 2) }} NOK</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Period</div>
                    <div class="text-lg font-semibold">{{ $overview['period']['from'] }} - {{ $overview['period']['to'] }}</div>
                </div>
            </div>
            
            <!-- Payment Method Breakdown -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-4">By Payment Method</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @foreach($overview['by_payment_method'] as $method => $data)
                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                            <div class="text-sm text-gray-500 dark:text-gray-400 capitalize">{{ $method }}</div>
                            <div class="text-xl font-bold">{{ number_format($data['amount'] / 100, 2) }} NOK</div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">{{ $data['count'] }} transactions</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-filament::section>

        <!-- Sessions Table -->
        <x-filament::section>
            <x-slot name="heading">
                POS Sessions
            </x-slot>
            <x-slot name="description">
                Detailed session information with X and Z reports
            </x-slot>
            
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
