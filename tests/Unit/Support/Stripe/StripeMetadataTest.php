<?php

declare(strict_types=1);

use App\Support\Stripe\StripeMetadata;
use Stripe\StripeObject;

it('converts stripe metadata objects to plain arrays', function (): void {
    $meta = StripeObject::constructFrom([
        'eventKey' => '534cbf13-7309-4e9b-bd57-8226cbab5846',
        'seats' => 'A1,A2',
    ]);

    expect(StripeMetadata::toArray($meta))->toBe([
        'eventKey' => '534cbf13-7309-4e9b-bd57-8226cbab5846',
        'seats' => 'A1,A2',
    ]);
});

it('returns null for empty metadata', function (): void {
    expect(StripeMetadata::toArray(null))->toBeNull()
        ->and(StripeMetadata::toArray([]))->toBeNull()
        ->and(StripeMetadata::toArray(StripeObject::constructFrom([])))->toBeNull();
});
