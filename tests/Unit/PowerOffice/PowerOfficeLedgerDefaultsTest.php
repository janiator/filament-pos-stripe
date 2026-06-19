<?php

use App\Support\PowerOffice\PowerOfficeLedgerDefaults;

it('maps Jobberiet collection names to sales accounts', function () {
    expect(PowerOfficeLedgerDefaults::salesAccountForCollectionName('Neverstua'))->toBe('3000')
        ->and(PowerOfficeLedgerDefaults::salesAccountForCollectionName('Vaskeri'))->toBe('3020')
        ->and(PowerOfficeLedgerDefaults::salesAccountForCollectionName('Uteavdelingen'))->toBe('3022')
        ->and(PowerOfficeLedgerDefaults::salesAccountForCollectionName('Stuttreist'))->toBe('3023')
        ->and(PowerOfficeLedgerDefaults::salesAccountForCollectionName('Kantine (0% mva)'))->toBe('5910');
});

it('provides ledger form defaults from planning values', function () {
    $defaults = PowerOfficeLedgerDefaults::ledgerFormDefaults();

    expect($defaults['ledger_department_no'])->toBe('20')
        ->and($defaults['ledger_commission_revenue_account_no'])->toBe('3023')
        ->and($defaults['ledger_default_sales_account_no'])->toBe('3000')
        ->and($defaults['ledger_vipps_fee_debit_account_no'])->toBe('7720')
        ->and($defaults['ledger_shared_vat_account_no'])->toBe('2700')
        ->and($defaults['ledger_shared_tips_account_no'])->toBe('3001')
        ->and($defaults['ledger_payment_debit_cash'])->toBe('1920')
        ->and($defaults['ledger_payment_debit_card'])->toBe('1921')
        ->and($defaults['ledger_fee_credit_account_no'])->toBe('2900')
        ->and($defaults['ledger_fee_debit_account_no'])->toBe('7900')
        ->and($defaults['vat_sales_0'])->toBe('5910')
        ->and($defaults['vat_sales_25'])->toBe('3000');
});

it('keeps saved form values over defaults', function () {
    $merged = PowerOfficeLedgerDefaults::mergeFormDefaults([
        'ledger_department_no' => '99',
        'ledger_vipps_fee_debit_account_no' => '',
    ]);

    expect($merged['ledger_department_no'])->toBe('99')
        ->and($merged['ledger_vipps_fee_debit_account_no'])->toBe('7720');
});
