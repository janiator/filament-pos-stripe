<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeMappingBasis;
use App\Filament\Resources\PowerOfficeIntegrations\Pages\ManagePowerOfficeIntegration;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Support\PowerOffice\PowerOfficeLedgerSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'ViewAny:PowerOfficeIntegration', 'guard_name' => 'web']);
    $role->givePermissionTo('ViewAny:PowerOfficeIntegration');

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

it('shows vendor overview instead of per-vendor mapping repeater', function () {
    Vendor::query()->create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Stuttreist',
        'active' => true,
        'supplier_ledger_account_number' => '40001',
        'commission_percent' => 10,
    ]);

    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $this->store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vendor,
    ]);

    livewire(ManagePowerOfficeIntegration::class)
        ->assertOk()
        ->assertSee(__('Vendor revenue accounts'))
        ->assertSee('Stuttreist')
        ->assertSee('40001')
        ->assertDontSee(__('Revenue account per product collection'));
});

it('persists a single shared mapping row when saving vendor basis settings', function () {
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $this->store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vendor,
    ]);

    livewire(ManagePowerOfficeIntegration::class)
        ->fillForm([
            'mapping_basis' => PowerOfficeMappingBasis::Vendor->value,
            'sync_enabled' => true,
            'auto_sync_on_z_report' => true,
            'environment' => $integration->environment->value,
            'ledger_shared_vat_account_no' => '2700',
            'ledger_shared_tips_account_no' => '3001',
            'ledger_shared_cash_account_no' => '1920',
            'ledger_shared_card_clearing_account_no' => '1921',
            'ledger_default_sales_account_no' => '3000',
            'ledger_department_no' => '20',
            'ledger_commission_revenue_account_no' => '3023',
            'mappings' => [],
        ])
        ->call('saveSettings')
        ->assertNotified();

    $integration->refresh()->load('accountMappings');

    expect($integration->accountMappings)->toHaveCount(1)
        ->and($integration->accountMappings->first()->basis_key)->toBe(PowerOfficeLedgerSettings::SHARED_MAPPING_BASIS_KEY)
        ->and($integration->accountMappings->first()->vat_account_no)->toBe('2700')
        ->and($integration->accountMappings->first()->sales_account_no)->toBe('3000');
});
