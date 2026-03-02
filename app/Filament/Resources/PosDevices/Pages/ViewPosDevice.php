<?php

namespace App\Filament\Resources\PosDevices\Pages;

use App\Filament\Resources\PosDevices\PosDeviceResource;
use App\Actions\SafT\GenerateSafTCashRegister;
use App\Actions\SafT\ValidateSafTCashRegister;
use App\Actions\SafT\AnonymizeSafTForTestSubmission;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ViewPosDevice extends ViewRecord
{
    protected static string $resource = PosDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('export_journal')
                ->label('Export Journal (SAF-T)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    DatePicker::make('from_date')
                        ->label('From Date')
                        ->required()
                        ->default(now()->startOfMonth())
                        ->maxDate(now()),
                    DatePicker::make('to_date')
                        ->label('To Date')
                        ->required()
                        ->default(now())
                        ->maxDate(now())
                        ->after('from_date'),
                ])
                ->action(function (array $data): void {
                    /** @var \App\Models\PosDevice $posDevice */
                    $posDevice = $this->getRecord();
                    $store = $posDevice->store;

                    try {
                        $generator = new GenerateSafTCashRegister();
                        $xmlContent = $generator(
                            $store,
                            $data['from_date'],
                            $data['to_date']
                        );

                        // Generate filename
                        $filename = sprintf(
                            'SAF-T_%s_%s_%s.xml',
                            $store->slug,
                            $data['from_date'],
                            $data['to_date']
                        );

                        // Store file temporarily
                        $path = 'saf-t/' . $filename;
                        Storage::put($path, $xmlContent);

                        // Generate signed URL (valid for 24 hours)
                        // Use absolute URL to ensure proper signature validation
                        $downloadUrl = URL::temporarySignedRoute(
                            'api.saf-t.download',
                            now()->addHours(24),
                            ['filename' => $filename],
                            absolute: true
                        );
                        
                        Notification::make()
                            ->title('Journal export generated')
                            ->success()
                            ->body('The SAF-T file has been generated successfully. Opening download...')
                            ->send();
                        
                        // Open download in new tab using JavaScript
                        // Use json_encode to properly escape the URL for JavaScript without breaking query parameters
                        $this->js("window.open(" . json_encode($downloadUrl) . ", '_blank')");
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to export journal')
                            ->danger()
                            ->body('An error occurred while generating the SAF-T file: ' . $e->getMessage())
                            ->send();
                    }
                })
                ->modalHeading('Export Electronic Journal (SAF-T)')
                ->modalDescription('Generate and download a SAF-T file containing all transactions and events for the selected date range.')
                ->modalSubmitActionLabel('Export'),
            Actions\Action::make('validate_saf_t')
                ->label('Validate SAF-T')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->form([
                    DatePicker::make('from_date')
                        ->label('From Date')
                        ->required()
                        ->default(now()->startOfMonth())
                        ->maxDate(now()),
                    DatePicker::make('to_date')
                        ->label('To Date')
                        ->required()
                        ->default(now())
                        ->maxDate(now())
                        ->after('from_date'),
                ])
                ->action(function (array $data): void {
                    $posDevice = $this->getRecord();
                    $store = $posDevice->store;
                    try {
                        $generator = new GenerateSafTCashRegister();
                        $xmlContent = $generator($store, $data['from_date'], $data['to_date']);
                        $validator = new ValidateSafTCashRegister();
                        $result = $validator($xmlContent);
                        if ($result['valid']) {
                            Notification::make()
                                ->title('SAF-T validation passed')
                                ->success()
                                ->body('The generated file is valid against the Norwegian SAF-T Cash Register schema.')
                                ->send();
                        } else {
                            $msg = collect($result['errors'])->take(5)->map(fn ($e) => ($e['line'] ?? null) ? "Line {$e['line']}: {$e['message']}" : $e['message'])->join("\n");
                            if (count($result['errors']) > 5) {
                                $msg .= "\n… and ".(count($result['errors']) - 5).' more.';
                            }
                            Notification::make()
                                ->title('SAF-T validation failed')
                                ->danger()
                                ->body($msg)
                                ->persistent()
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Validation error')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->modalHeading('Validate SAF-T against schema')
                ->modalDescription('Generate SAF-T for a date range and validate it against the official Norwegian schema (Skatteetaten).')
                ->modalSubmitActionLabel('Validate'),
            Actions\Action::make('prepare_test_submission')
                ->label('Prepare for test submission')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->form([
                    DatePicker::make('from_date')
                        ->label('From Date')
                        ->required()
                        ->default(now()->startOfMonth())
                        ->maxDate(now()),
                    DatePicker::make('to_date')
                        ->label('To Date')
                        ->required()
                        ->default(now())
                        ->maxDate(now())
                        ->after('from_date'),
                ])
                ->action(function (array $data): void {
                    $posDevice = $this->getRecord();
                    $store = $posDevice->store;
                    try {
                        $generator = new GenerateSafTCashRegister();
                        $xmlContent = $generator($store, $data['from_date'], $data['to_date']);
                        $anonymize = new AnonymizeSafTForTestSubmission();
                        $xmlContent = $anonymize($xmlContent);
                        $filename = sprintf(
                            'SAF-T_TEST_%s_%s_%s.xml',
                            $store->slug,
                            $data['from_date'],
                            $data['to_date']
                        );
                        $path = 'saf-t/'.$filename;
                        Storage::put($path, $xmlContent);
                        $downloadUrl = URL::temporarySignedRoute(
                            'api.saf-t.download',
                            now()->addHours(24),
                            ['filename' => $filename],
                            absolute: true
                        );
                        Notification::make()
                            ->title('File prepared for test submission')
                            ->success()
                            ->body('Anonymized SAF-T file ready. Use reference number '.config('saf_t.test_reference_number', '2025/5012202').' when submitting via Altinn TT02.')
                            ->send();
                        $this->js('window.open('.json_encode($downloadUrl).", '_blank')");
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to prepare file')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->modalHeading('Prepare SAF-T for Skatteetaten test')
                ->modalDescription('Generate an anonymized SAF-T file for test submission via Altinn TT02. Use only synthetic test data. Reference number: '.config('saf_t.test_reference_number', '2025/5012202').'. See: '.config('saf_t.test_submission_url'))
                ->modalSubmitActionLabel('Generate and download'),
        ];
    }
}

