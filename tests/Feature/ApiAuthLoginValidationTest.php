<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('returns a Norwegian summary message for wrong password', function (): void {
    User::factory()->create([
        'email' => 'cashier@example.com',
        'password' => Hash::make('secret'),
    ]);

    $expected = 'E-postadressen eller passordet er feil.';

    $response = $this->postJson('/api/auth/login', [
        'email' => 'cashier@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', $expected)
        ->assertJsonPath('errors.email.0', $expected);
});

it('returns the same credential error for unknown email', function (): void {
    $expected = 'E-postadressen eller passordet er feil.';

    $response = $this->postJson('/api/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'any',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', $expected)
        ->assertJsonPath('errors.email.0', $expected);
});
