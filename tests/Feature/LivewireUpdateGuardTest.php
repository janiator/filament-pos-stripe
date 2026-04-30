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
