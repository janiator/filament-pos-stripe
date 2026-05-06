<?php

declare(strict_types=1);

use App\Filament\Resources\Users\UserResource;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $this->storeA = Store::factory()->create(['slug' => 'store-a-'.fake()->unique()->numerify('####')]);
    $this->storeB = Store::factory()->create(['slug' => 'store-b-'.fake()->unique()->numerify('####')]);

    $this->admin = User::factory()->create();
    $this->admin->stores()->attach([$this->storeA->id]);
    $this->admin->assignRole('super_admin');

    $this->userOnAOnly = User::factory()->create();
    $this->userOnAOnly->stores()->attach([$this->storeA->id]);

    $this->userOnBOnly = User::factory()->create();
    $this->userOnBOnly->stores()->attach([$this->storeB->id]);
});

it('scopes the users resource query to users attached to the current tenant store', function (): void {
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('app');
    Filament::setTenant($this->storeA);
    Filament::bootCurrentPanel();

    $ids = UserResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($this->admin->id, $this->userOnAOnly->id)
        ->not->toContain($this->userOnBOnly->id);
});

it('scopes to the other store when the tenant is switched', function (): void {
    $this->actingAs($this->admin);
    $this->admin->stores()->syncWithoutDetaching([$this->storeA->id, $this->storeB->id]);

    Filament::setCurrentPanel('app');
    Filament::setTenant($this->storeB);
    Filament::bootCurrentPanel();

    $ids = UserResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($this->userOnBOnly->id)
        ->not->toContain($this->userOnAOnly->id);
});
