<?php

declare(strict_types=1);

use App\Enums\AddonType;
use App\Filament\Resources\ConnectedProducts\Pages\ListConnectedProducts;
use App\Models\Addon;
use App\Models\ConnectedProduct;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    foreach (['ViewAny:ConnectedProduct', 'View:ConnectedProduct', 'Update:ConnectedProduct'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
    }

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_products_'.fake()->uuid(),
    ]);
    $this->user->stores()->attach($this->store);
    $this->user->assignRole('super_admin');

    Addon::factory()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::Pos,
        'is_active' => true,
    ]);

    $this->actingAs($this->user);
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();
});

it('lists only products for the current store tenant', function (): void {
    Store::factory()->create([
        'stripe_account_id' => 'acct_other_list_scope',
    ]);

    $inTenant = ConnectedProduct::factory()->create([
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'UniqueListProductAlpha',
        'price' => '99.50',
    ]);

    $outsideTenant = ConnectedProduct::factory()->create([
        'stripe_account_id' => 'acct_other_list_scope',
        'name' => 'OtherStoreProduct',
    ]);

    livewire(ListConnectedProducts::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$inTenant])
        ->assertCanNotSeeTableRecords([$outsideTenant]);
});
