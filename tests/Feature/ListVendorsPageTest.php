<?php

declare(strict_types=1);

use App\Enums\AddonType;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Models\Addon;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    foreach (['ViewAny:Vendor', 'View:Vendor', 'Update:Vendor', 'Delete:Vendor'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
    }

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_vendors_'.fake()->uuid(),
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

it('lists only vendors for the current store tenant', function (): void {
    $otherStore = Store::factory()->create([
        'stripe_account_id' => 'acct_other_vendor_list',
    ]);

    $inTenant = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Tenant Vendor Alpha',
        'active' => true,
    ]);

    $outsideTenant = Vendor::create([
        'store_id' => $otherStore->id,
        'stripe_account_id' => $otherStore->stripe_account_id,
        'name' => 'Other Store Vendor',
        'active' => true,
    ]);

    livewire(ListVendors::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$inTenant])
        ->assertCanNotSeeTableRecords([$outsideTenant]);
});

it('can bulk set commission percent on selected vendors', function (): void {
    $vendorA = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Vendor A',
        'active' => true,
        'commission_percent' => null,
    ]);

    $vendorB = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Vendor B',
        'active' => true,
        'commission_percent' => 5,
    ]);

    $archivedVendor = Vendor::create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Archived Vendor',
        'active' => true,
        'commission_percent' => null,
        'archived_at' => now(),
    ]);

    livewire(ListVendors::class)
        ->assertOk()
        ->loadTable()
        ->callTableBulkAction('setCommissionPercent', [$vendorA, $vendorB, $archivedVendor], [
            'commission_percent' => 12.5,
        ])
        ->assertNotified();

    expect($vendorA->fresh()->commission_percent)->toBe('12.50');
    expect($vendorB->fresh()->commission_percent)->toBe('12.50');
    expect($archivedVendor->fresh()->commission_percent)->toBeNull();
});
