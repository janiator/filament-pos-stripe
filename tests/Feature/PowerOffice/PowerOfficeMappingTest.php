<?php

use App\Enums\PowerOfficeMappingBasis;
use App\Exceptions\PowerOffice\MissingPowerOfficeMappingException;
use App\Models\PosSession;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throws when required account mappings are missing', function () {
    $store = Store::factory()->create();
    $integration = PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
    ]);

    $session = PosSession::factory()->create([
        'store_id' => $store->id,
        'status' => 'closed',
        'closed_at' => now(),
        'closing_data' => [
            'z_report_data' => [
                'net_amount' => 10000,
                'vat_amount' => 2000,
                'vat_rate' => 25,
                'total_tips' => 0,
                'net_cash_amount' => 10000,
                'net_card_amount' => 0,
                'net_mobile_amount' => 0,
                'net_other_amount' => 0,
                'store' => ['id' => $store->id, 'name' => $store->name],
            ],
        ],
    ]);

    $builder = app(PowerOfficeLedgerPayloadBuilder::class);

    expect(fn () => $builder->build($session, $integration, $session->closing_data['z_report_data']))
        ->toThrow(MissingPowerOfficeMappingException::class);
});
