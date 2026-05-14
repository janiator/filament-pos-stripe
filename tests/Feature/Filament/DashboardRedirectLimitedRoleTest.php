<?php

declare(strict_types=1);

use App\Filament\Resources\PosSessions\PosSessionResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'ViewAny:PosSession', 'guard_name' => 'web']);

    $role = Role::firstOrCreate(['name' => 'pos_only', 'guard_name' => 'web']);
    $role->syncPermissions(['ViewAny:PosSession']);

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.fake()->uuid(),
    ]);
    $this->user->stores()->attach($this->store);
    $this->user->assignRole('pos_only');
});

it('redirects the tenant dashboard to the first accessible resource when the user lacks dashboard permission', function (): void {
    $this->actingAs($this->user);

    $response = $this->get(route('filament.app.pages.dashboard', ['tenant' => $this->store]));

    $response->assertRedirect(PosSessionResource::getUrl('index', [], true, 'app', $this->store));
});
