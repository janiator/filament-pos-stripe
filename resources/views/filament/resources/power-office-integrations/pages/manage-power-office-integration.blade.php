<x-filament-panels::page>
    @if(! $this->shouldShowSettings())
        <div class="mx-auto max-w-3xl space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Connect PowerOffice') }}
                </x-slot>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Sign in to PowerOffice Go and approve this integration. When you are done, click Refresh status — you will then configure ledger accounts on the settings page.') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        type="button"
                        wire:click="startOnboardingWizard"
                        class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-outlined-label relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-transparent px-3 py-2 text-sm font-semibold text-white shadow-sm transition duration-75 outline-none bg-primary-600 hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400"
                    >
                        {{ __('Open PowerOffice sign-in') }}
                    </button>
                    <button
                        type="button"
                        wire:click="refreshIntegration"
                        class="fi-btn fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-x-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition duration-75 outline-none hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                    >
                        {{ __('Refresh status') }}
                    </button>
                </div>
                <p class="mt-4 text-sm font-medium">
                    {{ __('Status') }}:
                    @if($this->integration->isConnected())
                        <span class="text-success-600 dark:text-success-400">{{ __('Connected') }}</span>
                    @else
                        <span class="text-gray-600 dark:text-gray-400">{{ __('Not connected yet') }}</span>
                    @endif
                </p>
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
                {{ __('Recent syncs') }}
            </x-slot>
            @if($this->recentSyncRuns()->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No sync runs yet.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left dark:border-white/10">
                                <th class="pb-2 pr-4 font-medium">{{ __('Session') }}</th>
                                <th class="pb-2 pr-4 font-medium">{{ __('Status') }}</th>
                                <th class="pb-2 pr-4 font-medium">{{ __('Finished') }}</th>
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
