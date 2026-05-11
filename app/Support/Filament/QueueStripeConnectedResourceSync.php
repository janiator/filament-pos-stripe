<?php

namespace App\Support\Filament;

use App\Models\Store;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

final class QueueStripeConnectedResourceSync
{
    /**
     * Queue one job per store. Multiple stores are wrapped in a single {@see Bus::batch} for Horizon visibility.
     *
     * @param  callable(Store): ShouldQueue  $jobFactory
     * @param  EloquentCollection<int, Store>|Collection<int, Store>|null  $stores
     */
    public static function dispatch(
        string $batchName,
        string $resourceLabelPlural,
        callable $jobFactory,
        EloquentCollection|Collection|null $stores = null,
    ): int {
        if ($stores === null) {
            $stores = Store::getStoresForSync();
        }

        /** @var list<ShouldQueue> $jobs */
        $jobs = $stores
            ->filter(fn (Store $store): bool => filled($store->stripe_account_id))
            ->map(fn (Store $store): ShouldQueue => $jobFactory($store))
            ->values()
            ->all();

        $count = count($jobs);

        if ($count === 0) {
            Notification::make()
                ->title('No stores to sync')
                ->body('No connected Stripe accounts were found for the current context.')
                ->warning()
                ->send();

            return 0;
        }

        if ($count === 1) {
            dispatch($jobs[0]);
        } else {
            Bus::batch($jobs)
                ->name($batchName)
                ->dispatch();
        }

        Notification::make()
            ->title('Sync queued')
            ->body(
                $count === 1
                    ? "The {$resourceLabelPlural} sync has been queued and will run in the background."
                    : "{$count} background jobs have been queued as a batch ({$resourceLabelPlural}) for each connected Stripe account. You can monitor progress in the queue dashboard."
            )
            ->success()
            ->send();

        return $count;
    }
}
