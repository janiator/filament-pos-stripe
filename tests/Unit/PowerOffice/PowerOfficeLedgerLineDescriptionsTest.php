<?php

use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\Vendor;
use App\Support\PowerOffice\PowerOfficeLedgerLineDescriptions;

it('builds norwegian voucher header without per-line z-report prefix', function () {
    $session = new PosSession(['session_number' => '000122']);

    expect(PowerOfficeLedgerLineDescriptions::voucher($session))
        ->toBe('POS Z-rapport 000122');
});

it('uses pos device name for kasse payment descriptions', function () {
    $session = new PosSession;
    $session->setRelation('posDevice', new PosDevice(['device_name' => 'Bjarkelunden']));

    expect(PowerOfficeLedgerLineDescriptions::payment('card_present', $session))
        ->toBe('Kasse Bjarkelunden')
        ->and(PowerOfficeLedgerLineDescriptions::payment('vipps', $session))
        ->toBe('Kasse Bjarkelunden Vipps');
});

it('uses vendor name for reskontro and salg for commission lines', function () {
    $vendor = new Vendor(['name' => 'Lise Solvang']);

    expect(PowerOfficeLedgerLineDescriptions::vendorName($vendor))
        ->toBe('Lise Solvang')
        ->and(PowerOfficeLedgerLineDescriptions::vendorCommission($vendor))
        ->toBe('Salg Lise Solvang');
});
