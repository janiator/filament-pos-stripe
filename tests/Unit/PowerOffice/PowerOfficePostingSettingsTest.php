<?php

use App\Enums\PowerOfficeVoucherPostingMode;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Support\PowerOffice\PowerOfficePostingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults to direct posting when no store setting is stored', function () {
    config(['poweroffice.ledger.post_path' => '/Vouchers/ManualJournals']);

    $integration = PowerOfficeIntegration::factory()->create([
        'store_id' => Store::factory(),
        'settings' => null,
    ]);

    expect(PowerOfficePostingSettings::mode($integration))->toBe(PowerOfficeVoucherPostingMode::Direct)
        ->and(PowerOfficePostingSettings::ledgerPostPath($integration))->toBe('/Vouchers/ManualJournals');
});

it('uses journal entry path when store setting requests draft workflow', function () {
    config(['poweroffice.ledger.post_path' => '/Vouchers/ManualJournals']);

    $integration = PowerOfficeIntegration::factory()->create([
        'store_id' => Store::factory(),
        'settings' => ['voucher_posting_mode' => PowerOfficeVoucherPostingMode::JournalEntry->value],
    ]);

    expect(PowerOfficePostingSettings::usesDirectPosting($integration))->toBeFalse()
        ->and(PowerOfficePostingSettings::ledgerPostPath($integration))->toBe('/JournalEntryVouchers/ManualJournals');
});

it('falls back to global env path when store setting is missing', function () {
    config(['poweroffice.ledger.post_path' => '/JournalEntryVouchers/ManualJournals']);

    $integration = PowerOfficeIntegration::factory()->create([
        'store_id' => Store::factory(),
        'settings' => [],
    ]);

    expect(PowerOfficePostingSettings::mode($integration))->toBe(PowerOfficeVoucherPostingMode::JournalEntry);
});

it('maps direct toggle to posting mode enum', function () {
    expect(PowerOfficePostingSettings::modeFromDirectToggle(true))->toBe(PowerOfficeVoucherPostingMode::Direct)
        ->and(PowerOfficePostingSettings::modeFromDirectToggle(false))->toBe(PowerOfficeVoucherPostingMode::JournalEntry);
});
