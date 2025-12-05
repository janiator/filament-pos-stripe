<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('filament.widgets.sync_everything.heading') }}
        </x-slot>

        <x-slot name="description">
            @php
                $tenant = \Filament\Facades\Filament::getTenant();
                $isAdmin = $tenant && $tenant->slug === 'visivo-admin';
            @endphp
            @if($isAdmin)
                {{ __('filament.widgets.sync_everything.description_admin') }}
            @else
                {{ __('filament.widgets.sync_everything.description_store') }}
            @endif
        </x-slot>

        <div class="flex items-center gap-4">
            <x-filament::button
                wire:click="syncEverything"
                wire:confirm="{{ $isAdmin ? __('filament.widgets.sync_everything.confirm_admin') : __('filament.widgets.sync_everything.confirm_store') }}"
                icon="heroicon-o-arrow-path"
                color="gray"
            >
                {{ __('filament.widgets.sync_everything.button') }}
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

