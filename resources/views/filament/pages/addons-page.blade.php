<x-filament-panels::page>
    <div class="fi-addons-page-cards grid gap-6 sm:grid-cols-1 lg:grid-cols-2">
        @foreach($this->getAddonTypesWithStatus() as $item)
            @php
                $type = $item['type'];
                $addon = $item['addon'];
                $isOn = $addon && $addon->is_active;
                $hasWebflow = in_array($type->value, $this->typesWithWebflow(), true);
                $webflowSitesCount = $item['webflowSitesCount'];
            @endphp
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white py-[15px] shadow-sm dark:border-gray-700 dark:bg-gray-800">
                {{-- Card header: title + status --}}
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $type->label() }}
                    </h3>
                    @if($isOn)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-success-200 bg-success-50 px-1.5 py-1 text-xs font-medium text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-success-500 dark:bg-success-400" aria-hidden="true"></span>
                            On
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-1.5 py-1 text-xs font-medium text-gray-600 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400 dark:bg-gray-500" aria-hidden="true"></span>
                            Off
                        </span>
                    @endif
                </div>

                {{-- Card body: description + optional sites count --}}
                <div class="px-6 py-4">
                    <p class="pt-0.5 pb-0.5 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                        {{ $type->description() }}
                    </p>
                    @if($isOn && $hasWebflow)
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            {{ $webflowSitesCount === 0
                                ? 'No Webflow sites connected.'
                                : trans_choice(':count Webflow site connected.|:count Webflow sites connected.', $webflowSitesCount, ['count' => $webflowSitesCount]) }}
                        </p>
                    @endif
                </div>

                {{-- Card footer: actions --}}
                <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 bg-gray-50/80 px-6 py-1.5 dark:border-gray-700 dark:bg-gray-800/80">
                    @if($isOn)
                        @if($hasWebflow)
                            <a
                                href="{{ $this->getWebflowSiteCreateUrl() }}"
                                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus-visible:outline focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus-visible:ring-primary-400"
                            >
                                Add site
                            </a>
                            <a
                                href="{{ $this->getWebflowSitesUrl() }}"
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-1.5 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus-visible:outline focus-visible:ring-2 focus-visible:ring-gray-950/10 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus-visible:ring-white/20"
                            >
                                Manage sites
                            </a>
                        @else
                            @php $primary = $this->getPrimaryActionForType($type); @endphp
                            @if($primary)
                                <a
                                    href="{{ $primary['url'] }}"
                                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus-visible:outline focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus-visible:ring-primary-400"
                                >
                                    {{ $primary['label'] }}
                                </a>
                            @endif
                        @endif
                        <button
                            type="button"
                            wire:click="disableAddon('{{ $type->value }}')"
                            wire:confirm="Disable {{ $type->label() }}? The related menu and features will be hidden."
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-1.5 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline focus-visible:ring-2 focus-visible:ring-gray-950/10 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 dark:focus-visible:ring-white/20"
                        >
                            Disable
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="enableAddon('{{ $type->value }}')"
                            class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-2 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus-visible:outline focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus-visible:ring-primary-400"
                        >
                            Enable
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
