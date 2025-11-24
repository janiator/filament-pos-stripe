<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Sync Everything from Stripe
        </x-slot>

        <x-slot name="description">
            @php
                $tenant = \Filament\Facades\Filament::getTenant();
                $isAdmin = $tenant && $tenant->slug === 'visivo-admin';
            @endphp
            @if($isAdmin)
                Sync all customers, products, subscriptions, payment intents, charges, transfers, payment methods, payment links, and terminals from all connected Stripe accounts.
            @else
                Sync all customers, products, subscriptions, payment intents, charges, transfers, payment methods, payment links, and terminals from the current store's Stripe account.
            @endif
        </x-slot>

        <div class="flex items-center gap-4">
            <x-filament::button
                wire:click="syncEverything"
                wire:confirm="{{ $isAdmin ? 'This will sync all customers, products, subscriptions, payment intents, charges, transfers, payment methods, payment links, and terminals from all connected Stripe accounts. This may take a moment. Continue?' : 'This will sync all customers, products, subscriptions, payment intents, charges, transfers, payment methods, payment links, and terminals from the current store\'s Stripe account. This may take a moment. Continue?' }}"
                icon="heroicon-o-arrow-path"
                color="gray"
            >
                Sync Everything
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

