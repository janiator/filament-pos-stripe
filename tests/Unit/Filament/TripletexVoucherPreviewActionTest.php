<?php

use App\Filament\Actions\TripletexVoucherPreviewAction;
use Filament\Schemas\Components\Utilities\Get;
use Tests\TestCase;

uses(TestCase::class);

it('resolveJsonForZReportPreview returns session not found when record id resolves empty', function (): void {
    $get = Mockery::mock(Get::class);
    $get->allows('__invoke')->with('_record_id')->andReturn(0);

    $method = new ReflectionMethod(TripletexVoucherPreviewAction::class, 'resolveJsonForZReportPreview');
    expect($method->isStatic())->toBeTrue();
    $method->setAccessible(true);

    $json = $method->invoke(null, $get, false);
    $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

    expect($data['ok'])->toBeFalse()
        ->and($data['error'])->toBe('Session not found.');
});

it('resolveJsonForPayoutPreview returns payout not found when record id resolves empty', function (): void {
    $get = Mockery::mock(Get::class);
    $get->allows('__invoke')->with('_record_id')->andReturn(0);

    $method = new ReflectionMethod(TripletexVoucherPreviewAction::class, 'resolveJsonForPayoutPreview');
    $method->setAccessible(true);

    $json = $method->invoke(null, $get, false);
    $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

    expect($data['ok'])->toBeFalse()
        ->and($data['error'])->toBe('Payout not found.');
});
