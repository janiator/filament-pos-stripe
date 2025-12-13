<?php

namespace App\Filament\Resources\PosDevices\Pages;

use App\Filament\Resources\PosDevices\PosDeviceResource;
use App\Actions\SafT\GenerateSafTCashRegister;
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
        ];
    }
}

