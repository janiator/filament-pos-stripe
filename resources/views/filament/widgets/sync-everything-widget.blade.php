<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Sync Everything from Stripe
        </x-slot>

        <x-slot name="description">
            Sync all charges, transfers, payment methods, and payment links from all connected Stripe accounts.
        </x-slot>

        <div class="flex items-center gap-4">
            <x-filament::button
                wire:click="syncEverything"
                wire:confirm="This will sync all charges, transfers, payment methods, and payment links from all connected Stripe accounts. This may take a moment. Continue?"
                icon="heroicon-o-arrow-path"
                color="gray"
            >
                Sync Everything
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

