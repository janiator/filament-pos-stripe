<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('malformed livewire update payload is rejected early', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $response = $this->postJson('/livewire/update', [
        '_nightwatch_error' => 'NOT_ENABLED',
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Malformed Livewire update payload.');
});

test('non-json livewire update requests from probes are rejected early', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $response = $this->post('/livewire/update', [
        'components' => [
            [
                'snapshot' => '{}',
                'updates' => [],
                'calls' => [],
            ],
        ],
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Malformed Livewire update payload.');
});

test('livewire update json missing components shape is rejected early', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $response = $this->postJson('/livewire/update', [
        'components' => [],
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Malformed Livewire update payload.');
});
