<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeIntegrationStatus;
use App\Filament\Resources\PowerOfficeIntegrations\Pages\ManagePowerOfficeIntegration;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
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
    foreach (['ViewAny:PowerOfficeIntegration'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
    }

    $this->user = User::factory()->create();
    $this->store = Store::factory()->create([
        'stripe_account_id' => 'acct_test_'.fake()->uuid(),
    ]);
    $this->user->stores()->attach($this->store);
    $this->user->assignRole('super_admin');

    Addon::query()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    $this->actingAs($this->user);
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();
});

it('shows the connection screen when PowerOffice is not connected', function () {
    PowerOfficeIntegration::factory()->onboardingWizard()->create([
        'store_id' => $this->store->id,
    ]);

    livewire(ManagePowerOfficeIntegration::class)
        ->assertOk()
        ->assertSee(__('Connect PowerOffice'), false)
        ->assertSee(__('Open PowerOffice sign-in'), false)
        ->assertDontSee(__('PowerOffice sync enabled'));
});

it('loads the PowerOffice settings page when connected', function () {
    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $this->store->id,
    ]);

    livewire(ManagePowerOfficeIntegration::class)
        ->assertOk()
        ->assertSee(__('PowerOffice sync enabled'), false)
        ->assertDontSee(__('Connect PowerOffice'));
});

it('loads settings when connected even if onboarding timestamp is missing', function () {
    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $this->store->id,
        'onboarding_completed_at' => null,
    ]);

    livewire(ManagePowerOfficeIntegration::class)
        ->assertOk()
        ->assertSee(__('PowerOffice sync enabled'), false);
});

it('shows the connection screen when PowerOffice is not connected even if onboarding was marked complete', function () {
    PowerOfficeIntegration::factory()->create([
        'store_id' => $this->store->id,
        'status' => PowerOfficeIntegrationStatus::NotConnected,
        'client_key' => null,
        'onboarding_completed_at' => now(),
    ]);

    livewire(ManagePowerOfficeIntegration::class)
        ->assertOk()
        ->assertSee(__('Connect PowerOffice'), false)
        ->assertDontSee(__('PowerOffice sync enabled'));
});
