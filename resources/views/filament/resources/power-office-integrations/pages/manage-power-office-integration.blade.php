<x-filament-panels::page>
    @if(! $this->shouldShowSettings())
        <div class="mx-auto max-w-3xl space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    Step {{ $this->wizardStep }} of 3
                </x-slot>

                @if($this->wizardStep === 1)
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Sign in to PowerOffice Go and approve this integration. When you are done, click <strong>Refresh status</strong> (or reload the page), then continue.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button
                            type="button"
                            wire:click="startOnboardingWizard"
                            class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-outlined-label relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-transparent px-3 py-2 text-sm font-semibold text-white shadow-sm transition duration-75 outline-none bg-primary-600 hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400"
                        >
                            Open PowerOffice sign-in
                        </button>
                        <button
                            type="button"
                            wire:click="refreshIntegration"
                            class="fi-btn fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition duration-75 outline-none hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                        >
                            Refresh status
                        </button>
                    </div>
                    <p class="mt-4 text-sm font-medium">
                        Status:
                        @if($this->integration->isConnected())
                            <span class="text-success-600 dark:text-success-400">Connected</span>
                        @else
                            <span class="text-gray-600 dark:text-gray-400">Not connected yet</span>
                        @endif
                    </p>
                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            wire:click="wizardNext"
                            @disabled(! $this->integration->isConnected())
                            class="fi-btn fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-transparent px-3 py-2 text-sm font-semibold text-white shadow-sm transition duration-75 outline-none bg-primary-600 hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:pointer-events-none disabled:opacity-50 dark:bg-primary-500 dark:hover:bg-primary-400"
                        >
                            Next
                        </button>
                    </div>
                @endif

                @if($this->wizardStep === 2)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Choose how Z-report amounts should be split into ledger lines in PowerOffice (VAT rate, product collection, vendor, or payment method).
                    </p>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Sync type</label>
                        <select
                            wire:model.live="wizardMappingBasis"
                            class="fi-select-input block w-full rounded-lg border-gray-300 bg-white shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500 dark:disabled:bg-transparent dark:disabled:text-gray-400"
                        >
                            @foreach(\App\Enums\PowerOfficeMappingBasis::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            wire:click="wizardBack"
                            class="fi-btn fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition duration-75 outline-none hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            wire:click="wizardNext"
                            class="fi-btn fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-transparent px-3 py-2 text-sm font-semibold text-white shadow-sm transition duration-75 outline-none bg-primary-600 hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400"
                        >
                            Next
                        </button>
                    </div>
                @endif

                @if($this->wizardStep === 3)
                    @if($this->wizardMappingBasis === 'vat')
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Enter a <strong>sales / revenue account</strong> for each VAT rate you use. Below that, add <strong>one set</strong> of accounts for output VAT, payments (cash vs card), tips, fees, and rounding — these apply to the whole Z-report.
                        </p>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Enter PowerOffice account numbers for each line. You can use the same sales account for all rows if you prefer.
                        </p>
                    @endif
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Line</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">Sales account</th>
                                    @if($this->wizardMappingBasis !== 'vat')
                                        <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-200">VAT (optional)</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach($this->wizardMappingRows as $index => $row)
                                    <tr wire:key="wizard-row-{{ $row['basis_key'] }}">
                                        <td class="px-3 py-2 text-gray-900 dark:text-white">
                                            {{ $row['label'] }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                type="text"
                                                wire:model="wizardMappingRows.{{ $index }}.sales_account_no"
                                                class="fi-input block w-full rounded-lg border-gray-300 shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm"
                                                autocomplete="off"
                                            />
                                        </td>
                                        @if($this->wizardMappingBasis !== 'vat')
                                            <td class="px-3 py-2">
                                                <input
                                                    type="text"
                                                    wire:model="wizardMappingRows.{{ $index }}.vat_account_no"
                                                    class="fi-input block w-full rounded-lg border-gray-300 shadow-sm outline-none transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm"
                                                    autocomplete="off"
                                                />
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($this->wizardMappingBasis === 'vat')
                        <div class="mt-6 space-y-3 rounded-lg border border-gray-200 p-4 dark:border-white/10">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">VAT, tips, rounding &amp; payment fallbacks</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Use <strong>PowerOffice → Ledger routing</strong> after setup for per-method debits and PSP fee/payout pairs. Cash/card here are fallbacks.</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">VAT account (output)</label>
                                    <input type="text" wire:model="wizardSharedLedger.vat_account_no" class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm" autocomplete="off" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Tips account</label>
                                    <input type="text" wire:model="wizardSharedLedger.tips_account_no" class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm" autocomplete="off" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Cash account (fallback)</label>
                                    <input type="text" wire:model="wizardSharedLedger.cash_account_no" class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm" autocomplete="off" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Card / clearing (fallback)</label>
                                    <input type="text" wire:model="wizardSharedLedger.card_clearing_account_no" class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm" autocomplete="off" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Rounding account</label>
                                    <input type="text" wire:model="wizardSharedLedger.rounding_account_no" class="fi-input block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white sm:text-sm" autocomplete="off" />
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="mt-6 flex gap-3">
                        <button
                            type="button"
                            wire:click="wizardBack"
                            class="fi-btn fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition duration-75 outline-none hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            wire:click="completeWizard"
                            class="fi-btn fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-transparent px-3 py-2 text-sm font-semibold text-white shadow-sm transition duration-75 outline-none bg-primary-600 hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400"
                        >
                            Finish setup
                        </button>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @else
        <form wire:submit="saveSettings">
            {{ $this->form }}

            <div class="fi-form-actions mt-6">
                <div class="flex flex-wrap items-center gap-4">
                    @foreach($this->getCachedFormActions() as $action)
                        {{ $action }}
                    @endforeach
                </div>
            </div>
        </form>

        <x-filament::section class="mt-8">
            <x-slot name="heading">
                Recent syncs
            </x-slot>
            @if($this->recentSyncRuns()->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No sync runs yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                <th class="pb-2 pr-4 font-medium">Session</th>
                                <th class="pb-2 pr-4 font-medium">Status</th>
                                <th class="pb-2 pr-4 font-medium">Finished</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->recentSyncRuns() as $run)
                                <tr class="border-b border-gray-100 dark:border-white/5" wire:key="sync-run-{{ $run->id }}">
                                    <td class="py-2 pr-4">#{{ $run->pos_session_id }}</td>
                                    <td class="py-2 pr-4">{{ $run->status->label() }}</td>
                                    <td class="py-2 pr-4">{{ $run->finished_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
