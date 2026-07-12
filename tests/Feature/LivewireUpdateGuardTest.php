<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

test('malformed livewire update payload is rejected early', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $response = $this->postJson('/livewire/update', [
        '_nightwatch_error' => 'NOT_ENABLED',
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Malformed Livewire update payload.');
});

test('livewire update rejects invalid component envelope shapes', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $response = $this->postJson('/livewire/update', [
        'components' => [
            [
                'updates' => [],
                'calls' => [],
                'snapshot' => ['not-a-string'],
            ],
        ],
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Malformed Livewire update payload.');
});

test('notifications component rejects malformed isFilamentNotificationsComponent wire state before hydration', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $snapshot = json_encode([
        'memo' => [
            'id' => 'not-a-real-id',
            'name' => 'notifications',
        ],
        'data' => [
            'isFilamentNotificationsComponent' => ['malformed'],
        ],
        'checksum' => 'not-verified-but-exists-as-string-for-shape-check',
    ]);

    assert(is_string($snapshot));

    $response = $this->postJson('/livewire/update', [
        'components' => [
            [
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [],
            ],
        ],
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Malformed Livewire update payload.');
});
