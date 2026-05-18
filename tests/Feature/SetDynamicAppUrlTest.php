<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;

it('uses the forwarded host from expose when generating urls', function (): void {
    $this->get('/up', [
        'Host' => 'pos-stripe.test',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'pos-stripe.share.visivo.no',
    ])->assertOk();

    expect(URL::to('/app/login'))->toBe('https://pos-stripe.share.visivo.no/app/login');
});
