<?php

use App\Enums\AddonType;
use App\Enums\PowerOfficeMappingBasis;
use App\Filament\Resources\PowerOfficeIntegrations\Pages\ManagePowerOfficeIntegration;
use App\Models\Addon;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'poweroffice.client_id' => 'test-poweroffice-application-key',
        'poweroffice.subscription_key' => 'test-ocp-apim-subscription-key',
    ]);

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

    $this->integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $this->store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
    ]);

    PowerOfficeAccountMapping::factory()->create([
        'store_id' => $this->store->id,
        'power_office_integration_id' => $this->integration->id,
        'basis_type' => PowerOfficeMappingBasis::Vat,
        'basis_key' => '25',
        'sales_account_no' => '3000',
        'vat_account_no' => '2700',
        'tips_account_no' => null,
        'cash_account_no' => '1920',
        'card_clearing_account_no' => null,
        'rounding_account_no' => null,
    ]);

    Vendor::query()->create([
        'store_id' => $this->store->id,
        'stripe_account_id' => $this->store->stripe_account_id,
        'name' => 'Stuttreist',
        'active' => true,
        'commission_percent' => 10,
        'supplier_ledger_account_number' => '40001',
        'commission_revenue_account_number' => '3023',
    ]);

    // PowerOffice knows 3000 and 2700; 1920, 3023, and supplier 40001 are missing.
    Http::fake(function (Request $request) {
        if (str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($request->url(), 'GeneralLedgerAccounts')) {
            if ($request->method() === 'POST') {
                return Http::response(['Id' => 999, 'AccountNo' => data_get($request->data(), 'AccountNo')], 201);
            }

            return Http::response([
                ['Id' => 101, 'AccountNo' => 3000, 'VatCodeId' => 1],
                ['Id' => 102, 'AccountNo' => 2700, 'VatCodeId' => 1],
            ], 200);
        }

        if (str_contains($request->url(), 'Suppliers')) {
            if ($request->method() === 'POST') {
                return Http::response(['Id' => 555, 'Number' => data_get($request->data(), 'Number')], 201);
            }

            return Http::response(null, 204);
        }

        return Http::response([], 200);
    });
});

it('shows which configured account numbers exist in PowerOffice', function () {
    $component = livewire(ManagePowerOfficeIntegration::class)
        ->callAction('checkPowerOfficeAccounts')
        ->assertNotified();

    $status = $component->get('powerOfficeAccountStatus');

    $gl = collect($status['gl'])->keyBy('account_no');

    expect($gl['3000']['exists'])->toBeTrue()
        ->and($gl['2700']['exists'])->toBeTrue()
        ->and($gl['1920']['exists'])->toBeFalse()
        ->and($gl['3023']['exists'])->toBeFalse()
        ->and($gl['3023']['suggested_vat_code'])->toBe('3')
        ->and($gl['1920']['suggested_vat_code'])->toBe('0')
        ->and($status['suppliers'][0]['number'])->toBe('40001')
        ->and($status['suppliers'][0]['exists'])->toBeFalse();

    $component->assertSee('1920')->assertSee('40001')->assertSee(__('Missing'));
});

it('bulk creates missing GL accounts and suppliers through the PowerOffice api', function () {
    livewire(ManagePowerOfficeIntegration::class)
        ->callAction('checkPowerOfficeAccounts')
        ->callAction('createMissingPowerOfficeAccounts')
        ->assertNotified();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), 'GeneralLedgerAccounts')
        && (int) data_get($request->data(), 'AccountNo') === 1920
        && data_get($request->data(), 'VatCode') === '0');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), 'GeneralLedgerAccounts')
        && (int) data_get($request->data(), 'AccountNo') === 3023
        && data_get($request->data(), 'VatCode') === '3');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), 'Suppliers')
        && (int) data_get($request->data(), 'Number') === 40001
        && data_get($request->data(), 'Name') === 'Stuttreist');
});

it('reports detailed PowerOffice validation errors when supplier creation fails', function () {
    Http::fake(function (Request $request) {
        if (str_contains(strtolower($request->url()), 'oauth/token')) {
            return Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($request->url(), 'GeneralLedgerAccounts')) {
            if ($request->method() === 'POST') {
                return Http::response(['Id' => 999, 'AccountNo' => data_get($request->data(), 'AccountNo')], 201);
            }

            return Http::response([
                ['Id' => 101, 'AccountNo' => 3000, 'VatCodeId' => 1],
                ['Id' => 102, 'AccountNo' => 2700, 'VatCodeId' => 1],
            ], 200);
        }

        if (str_contains($request->url(), 'Suppliers')) {
            if ($request->method() === 'POST') {
                return Http::response([
                    'title' => 'Aggregated (multiple) Validation Exception',
                    'detail' => 'Supplier validation error(s)',
                    'errors' => [
                        'Number' => ['The supplier number must be within the supplier number series.'],
                    ],
                ], 400);
            }

            return Http::response(null, 204);
        }

        return Http::response([], 200);
    });

    livewire(ManagePowerOfficeIntegration::class)
        ->callAction('checkPowerOfficeAccounts')
        ->callAction('createMissingPowerOfficeAccounts')
        ->assertNotified();
});
