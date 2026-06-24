<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeMappingBasis;
use App\Filament\Resources\PowerOfficeIntegrations\Pages\ManagePowerOfficeIntegration;
use App\Models\Addon;
use App\Models\ArticleGroupCode;
use App\Models\Collection as ProductCollection;
use App\Models\PowerOfficeAccountMapping;
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

it('persists article group and collection mappings when saving hybrid category settings', function () {
    ArticleGroupCode::query()->create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'code' => '04001',
        'name' => 'Neverstua',
        'active' => true,
        'sort_order' => 0,
    ]);
    $collection = ProductCollection::query()->create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Neverstua',
        'handle' => 'neverstua',
        'active' => true,
        'sort_order' => 0,
    ]);

    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $this->store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Category,
    ]);

    livewire(ManagePowerOfficeIntegration::class)
        ->fillForm([
            'mapping_basis' => PowerOfficeMappingBasis::Category->value,
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
        ])
        ->set('data.article_group_mappings', [
            [
                'basis_key' => '04001',
                'sales_account_no' => '3000',
                'is_active' => true,
            ],
        ])
        ->set('data.mappings', [
            [
                'basis_key' => (string) $collection->id,
                'sales_account_no' => '3000',
                'is_active' => true,
            ],
        ])
        ->call('saveSettings')
        ->assertNotified();

    $integration->refresh()->load('accountMappings');

    $articleMapping = $integration->accountMappings
        ->first(fn (PowerOfficeAccountMapping $m): bool => $m->basis_type === PowerOfficeMappingBasis::ArticleGroup);
    $collectionMapping = $integration->accountMappings
        ->first(fn (PowerOfficeAccountMapping $m): bool => $m->basis_type === PowerOfficeMappingBasis::Category);

    expect($articleMapping)->not->toBeNull()
        ->and($articleMapping->basis_key)->toBe('04001')
        ->and($articleMapping->sales_account_no)->toBe('3000')
        ->and($collectionMapping)->not->toBeNull()
        ->and($collectionMapping->basis_key)->toBe((string) $collection->id)
        ->and($collectionMapping->sales_account_no)->toBe('3000');
});
