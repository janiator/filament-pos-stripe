<?php

use App\Enums\PowerOfficeMappingBasis;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the manual PowerOffice sync action for closed sessions even before a cached z-report snapshot exists', function () {
    $store = Store::factory()->create();

    PowerOfficeIntegration::factory()->connected()->create([
        'store_id' => $store->id,
        'mapping_basis' => PowerOfficeMappingBasis::Vat,
        'sync_enabled' => true,
    ]);

    $session = PosSession::factory()->forStore($store)->create([
        'status' => 'closed',
        'closing_data' => [],
    ]);

    $method = new ReflectionMethod(PosSessionsTable::class, 'canSyncToPowerOffice');
    $method->setAccessible(true);

    expect($method->invoke(null, $session))->toBeTrue();
});
