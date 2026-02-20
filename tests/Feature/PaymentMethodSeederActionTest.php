<?php

use App\Enums\AddonType;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\Addon;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'ViewAny:PaymentMethod', 'guard_name' => 'web']);
    $role->givePermissionTo('ViewAny:PaymentMethod');

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.fake()->uuid(),
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

it('can run the import defaults action and seed payment methods', function () {
    expect(PaymentMethod::where('store_id', $this->store->id)->count())->toBe(0);

    $component = livewire(ListPaymentMethods::class);
    $component->assertOk();
    $component->callAction('importDefaults');
    $component->assertNotified();

    expect(PaymentMethod::where('store_id', $this->store->id)->count())->toBeGreaterThan(0);
    expect(PaymentMethod::where('store_id', $this->store->id)->where('code', 'cash')->exists())->toBeTrue();
    expect(PaymentMethod::where('store_id', $this->store->id)->where('code', 'card_present')->exists())->toBeTrue();
    expect(PaymentMethod::where('store_id', $this->store->id)->where('code', 'vipps')->exists())->toBeTrue();
});

it('does not duplicate payment methods when run twice', function () {
    livewire(ListPaymentMethods::class)
        ->assertOk()
        ->callAction('importDefaults')
        ->assertNotified();

    $countAfterFirst = PaymentMethod::where('store_id', $this->store->id)->count();

    livewire(ListPaymentMethods::class)
        ->assertOk()
        ->callAction('importDefaults')
        ->assertNotified();

    $countAfterSecond = PaymentMethod::where('store_id', $this->store->id)->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});
