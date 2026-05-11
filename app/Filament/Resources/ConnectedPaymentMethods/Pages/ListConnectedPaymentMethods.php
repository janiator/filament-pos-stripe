<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Pages;

use App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource;
use App\Jobs\SyncStorePaymentMethodsFromStripeJob;
use App\Models\Store;
use App\Support\Filament\QueueStripeConnectedResourceSync;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListConnectedPaymentMethods extends ListRecords
{
    protected static string $resource = ConnectedPaymentMethodResource::class;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery()
            ->with(['store']);

        // Note: Customer relationship will be loaded but may not be filtered by account_id
        // This is acceptable as the relationship is defined to match on customer_id only
        if (class_exists(\App\Models\ConnectedCustomer::class)) {
            $query->with(['customer']);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Payment methods are created via Stripe.js or Payment Intents, not manually
            // CreateAction::make(),

            Action::make('syncFromStripe')
                ->label('Sync from Stripe')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync Payment Methods from Stripe')
                ->modalDescription(fn () => $this->getSyncDescription('payment methods'))
                ->action(function () {
                    QueueStripeConnectedResourceSync::dispatch(
                        'Sync payment methods from Stripe',
                        'payment methods',
                        fn (Store $store): SyncStorePaymentMethodsFromStripeJob => new SyncStorePaymentMethodsFromStripeJob($store),
                    );

                    $this->refresh();
                }),
        ];
    }

    protected function getSyncDescription(string $type): string
    {
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                return "This will sync all {$type} from the current team's Stripe account. The sync runs in the background and may take several minutes.";
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        return "This will sync all {$type} from all connected Stripe accounts. Jobs run in the background and may take several minutes.";
    }
}
