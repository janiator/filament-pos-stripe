<?php

declare(strict_types=1);

use App\Jobs\SyncStoreCustomersFromStripeJob;
use App\Models\Store;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

it('uses the Batchable trait so it can be dispatched inside Bus batches', function (): void {
    expect(class_uses_recursive(SyncStoreCustomersFromStripeJob::class))
        ->toContain(Batchable::class);
});

it('does not throw when composed into a pending bus batch', function (): void {
    expect(fn (): PendingBatch => new PendingBatch(
        new Container,
        Collection::wrap([
            new SyncStoreCustomersFromStripeJob(Mockery::mock(Store::class)),
        ]),
    ))->not->toThrow(RuntimeException::class);
});
