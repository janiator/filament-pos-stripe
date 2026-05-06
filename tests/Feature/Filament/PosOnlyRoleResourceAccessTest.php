<?php

declare(strict_types=1);

use App\Enums\AddonType;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\AddonsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ConnectedCharges\ConnectedChargeResource;
use App\Filament\Resources\PowerOfficeIntegrations\PowerOfficeIntegrationResource;
use App\Filament\Resources\StoreStripePayouts\StoreStripePayoutResource;
use App\Filament\Resources\TripletexIntegrations\TripletexIntegrationResource;
use App\Filament\Workflows\WorkflowResource;
use App\Models\Addon;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource;
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

    Addon::factory()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::Pos,
        'is_active' => true,
    ]);

    Addon::factory()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::PowerOfficeGo,
        'is_active' => true,
    ]);

    Addon::factory()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::Tripletex,
        'is_active' => true,
    ]);

    $this->actingAs($this->user);
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();
});

it('hides stripe payout navigation for a role with only pos session list access', function (): void {
    expect(StoreStripePayoutResource::canAccess())->toBeFalse();
});

it('hides power office when the add-on is on but the user lacks power office permissions', function (): void {
    expect(PowerOfficeIntegrationResource::canAccess())->toBeFalse();
});

it('hides tripletex when the add-on is on but the user lacks tripletex permissions', function (): void {
    expect(TripletexIntegrationResource::canAccess())->toBeFalse();
});

it('hides the add-ons page without addon management permission', function (): void {
    expect(AddonsPage::canAccess())->toBeFalse();
});

it('hides the analytics dashboard without the dashboard permission', function (): void {
    expect(Dashboard::canAccess())->toBeFalse();
});

it('hides webflow sites when the webflow add-on is on but the user lacks webflow permissions', function (): void {
    Permission::firstOrCreate(['name' => 'ViewAny:WebflowSite', 'guard_name' => 'web']);

    Addon::factory()->create([
        'store_id' => $this->store->id,
        'type' => AddonType::WebflowCms,
        'is_active' => true,
    ]);

    expect(WebflowSiteResource::canAccess())->toBeFalse();
});

it('hides the settings cluster when no settings-cluster resources are accessible', function (): void {
    expect(SettingsCluster::canAccessClusteredComponents())->toBeFalse();
});

it('clusters payments, integrations, and workflows under the settings cluster', function (): void {
    expect(ConnectedChargeResource::getCluster())->toBe(SettingsCluster::class)
        ->and(StoreStripePayoutResource::getCluster())->toBe(SettingsCluster::class)
        ->and(TripletexIntegrationResource::getCluster())->toBe(SettingsCluster::class)
        ->and(PowerOfficeIntegrationResource::getCluster())->toBe(SettingsCluster::class)
        ->and(WorkflowResource::getCluster())->toBe(SettingsCluster::class);
});
