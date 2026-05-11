<x-filament-panels::page>
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

    @if($this->tripletexPreview)
        <x-filament::section class="mt-8">
            <x-slot name="heading">
                Voucher preview (not posted to Tripletex)
            </x-slot>
            <x-slot name="description">
                @if($this->tripletexPreview['ok'] ?? false)
                    <span class="font-medium text-gray-700 dark:text-gray-200">
                        {{ ($this->tripletexPreview['kind'] ?? '') === 'payout' ? 'Payout' : 'Z-report' }}
                        @if(($this->tripletexPreview['kind'] ?? '') === 'payout')
                            #{{ $this->tripletexPreview['store_stripe_payout_id'] ?? '—' }}
                        @else
                            session #{{ $this->tripletexPreview['pos_session_id'] ?? '—' }}
                        @endif
                    </span>
                    @if($this->tripletexPreview['balanced'] ?? false)
                        <span class="ms-2 rounded-md bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Balanced</span>
                    @else
                        <span class="ms-2 rounded-md bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">Not balanced</span>
                    @endif
                @else
                    <span class="text-danger-600 dark:text-danger-400">{{ $this->tripletexPreview['error'] ?? 'Preview failed' }}</span>
                @endif
            </x-slot>
            <div class="mb-3 flex justify-end">
                <button
                    type="button"
                    wire:click="clearTripletexPreview"
                    class="fi-btn fi-btn-size-sm fi-btn-color-gray relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm"
                >
                    Clear preview
                </button>
            </div>

            @if($this->tripletexPreview['ok'] ?? false)
                <div class="space-y-6">
                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Ledger lines ({{ $this->tripletexPreview['currency'] ?? 'NOK' }})</h4>
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                            Document date: {{ $this->tripletexPreview['document_date'] ?? '—' }} ·
                            {{ $this->tripletexPreview['description'] ?? '' }}
                        </p>
                        @if(!empty($this->tripletexPreview['lines_display'] ?? []))
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-left dark:bg-white/5">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">Account</th>
                                            <th class="px-3 py-2 font-medium">Description</th>
                                            <th class="px-3 py-2 font-medium text-end">Debit</th>
                                            <th class="px-3 py-2 font-medium text-end">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->tripletexPreview['lines_display'] as $row)
                                            <tr class="border-t border-gray-100 dark:border-white/5" wire:key="tripletex-line-{{ $loop->index }}">
                                                <td class="px-3 py-1.5 font-mono">{{ $row['account'] ?? '' }}</td>
                                                <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ $row['description'] ?? '' }}</td>
                                                <td class="px-3 py-1.5 text-end font-mono tabular-nums">{{ ($row['debit'] ?? 0) > 0 ? number_format((float) $row['debit'], 2, '.', ' ') : '—' }}</td>
                                                <td class="px-3 py-1.5 text-end font-mono tabular-nums">{{ ($row['credit'] ?? 0) > 0 ? number_format((float) $row['credit'], 2, '.', ' ') : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Debit total: {{ number_format(($this->tripletexPreview['debit_total_minor'] ?? 0) / 100, 2) }}
                                · Credit total: {{ number_format(($this->tripletexPreview['credit_total_minor'] ?? 0) / 100, 2) }}
                            </p>
                        @endif
                    </div>

                    @if(($this->tripletexPreview['kind'] ?? '') === 'payout' && ! empty($this->tripletexPreview['payout_external_ticket_sales'] ?? null))
                        @php
                            $ext = $this->tripletexPreview['payout_external_ticket_sales'];
                        @endphp
                        <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-3 text-xs dark:border-white/10 dark:bg-white/5">
                            <h4 class="mb-2 font-semibold text-gray-950 dark:text-white">External / web ticket lines (payout)</h4>
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Feature enabled</dt> <dd class="inline font-medium">{{ ($ext['enabled'] ?? false) ? 'Yes' : 'No' }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Sales account set</dt> <dd class="inline font-medium">{{ ($ext['sales_account_configured'] ?? false) ? 'Yes' : 'No' }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Charge rows in payout</dt> <dd class="inline font-mono">{{ (int) ($ext['charge_balance_transactions'] ?? 0) }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Without POS session</dt> <dd class="inline font-mono">{{ (int) ($ext['charges_without_pos_session'] ?? 0) }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Matched for voucher</dt> <dd class="inline font-mono">{{ (int) ($ext['matched_for_voucher_lines'] ?? 0) }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Skipped (no local charge)</dt> <dd class="inline font-mono">{{ (int) ($ext['skipped_no_connected_charge'] ?? 0) }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Skipped (POS session)</dt> <dd class="inline font-mono">{{ (int) ($ext['skipped_linked_pos_session'] ?? 0) }}</dd></div>
                                <div><dt class="inline text-gray-500 dark:text-gray-400">Skipped (metadata / regex)</dt> <dd class="inline font-mono">{{ (int) ($ext['skipped_metadata_or_regex'] ?? 0) }}</dd></div>
                            </dl>
                            @if(! empty($ext['required_metadata_keys'] ?? []))
                                <p class="mt-2 text-gray-600 dark:text-gray-300">Required metadata keys (all must match): <span class="font-mono">{{ implode(', ', $ext['required_metadata_keys']) }}</span></p>
                            @elseif(! empty($ext['default_any_of_metadata_keys'] ?? []))
                                <p class="mt-2 text-gray-600 dark:text-gray-300">Default metadata rule (at least one): <span class="font-mono">{{ implode(', ', $ext['default_any_of_metadata_keys']) }}</span></p>
                            @endif
                            @if(! empty($ext['notes'] ?? []))
                                <ul class="mt-2 list-disc space-y-1 ps-4 text-gray-700 dark:text-gray-300">
                                    @foreach($ext['notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    @if(filled($this->tripletexPreview['resolve_error'] ?? null))
                        <div class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-900 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-100">
                            {{ $this->tripletexPreview['resolve_error'] }}
                        </div>
                    @endif

                    @if(!empty($this->tripletexPreview['tripletex_postings_display'] ?? []))
                        <div>
                            <h4 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Tripletex postings (resolved)</h4>
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-left dark:bg-white/5">
                                        <tr>
                                            <th class="px-3 py-2 font-medium">#</th>
                                            <th class="px-3 py-2 font-medium">Account</th>
                                            <th class="px-3 py-2 font-medium">Name</th>
                                            <th class="px-3 py-2 font-medium text-end">Amount</th>
                                            <th class="px-3 py-2 font-medium">Line description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->tripletexPreview['tripletex_postings_display'] as $p)
                                            <tr class="border-t border-gray-100 dark:border-white/5" wire:key="tripletex-posting-{{ $loop->index }}">
                                                <td class="px-3 py-1.5 font-mono">{{ $p['row'] ?? '—' }}</td>
                                                <td class="px-3 py-1.5 font-mono">{{ $p['account_number'] ?? '—' }}</td>
                                                <td class="px-3 py-1.5 text-gray-700 dark:text-gray-300">{{ $p['account_name'] ?? '—' }}</td>
                                                <td class="px-3 py-1.5 text-end font-mono tabular-nums">{{ isset($p['amount_gross']) ? number_format((float) $p['amount_gross'], 2, '.', ' ') : '—' }}</td>
                                                <td class="px-3 py-1.5 text-gray-600 dark:text-gray-400">{{ $p['description'] ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if(!empty($this->tripletexPreview['tripletex_voucher_payload'] ?? []))
                        <div>
                            <h4 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Full Tripletex voucher JSON (draft)</h4>
                            <pre class="max-h-96 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100 dark:bg-black/40">{{ json_encode($this->tripletexPreview['tripletex_voucher_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif
                </div>
            @elseif(!empty($this->tripletexPreview['z_report'] ?? null))
                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Z-report snapshot (for debugging):</p>
                <pre class="max-h-64 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100 dark:bg-black/40">{{ json_encode($this->tripletexPreview['z_report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif

            <details class="mt-4">
                <summary class="cursor-pointer text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Raw API response JSON</summary>
                <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100 dark:bg-black/40">{{ json_encode($this->tripletexPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        </x-filament::section>
    @endif

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
                            <th class="pb-2 pr-4 font-medium">Type</th>
                            <th class="pb-2 pr-4 font-medium">Ref</th>
                            <th class="pb-2 pr-4 font-medium">Status</th>
                            <th class="pb-2 pr-4 font-medium">Voucher</th>
                            <th class="pb-2 pr-4 font-medium">Finished</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->recentSyncRuns() as $run)
                            <tr class="border-b border-gray-100 dark:border-white/5" wire:key="tripletex-sync-run-{{ $run->id }}">
                                <td class="py-2 pr-4">{{ $run->sync_type->label() }}</td>
                                <td class="py-2 pr-4">
                                    @if($run->sync_type === \App\Enums\TripletexSyncType::ZReport)
                                        Session #{{ $run->pos_session_id }}
                                    @else
                                        Payout #{{ $run->store_stripe_payout_id }}
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $run->status->label() }}</td>
                                <td class="py-2 pr-4">{{ $run->tripletex_voucher_id ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $run->finished_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
