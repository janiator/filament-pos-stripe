<?php

namespace App\Filament\Resources\PosSessions\Pages;

use App\Actions\PosSessions\RegenerateZReports;
use App\Filament\Resources\PosSessions\PosSessionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use App\Models\Store;

class ListPosSessions extends ListRecords
{
    protected static string $resource = PosSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('regenerateZReports')
                ->label('Regenerate Z-Reports')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate Z-Reports')
                ->modalDescription('This will regenerate Z-reports for closed sessions and attempt to find missing data (charges, receipts, events) that may not have been properly linked.')
                ->form([
                    Select::make('store_id')
                        ->label('Store')
                        ->options(function () {
                            return Store::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->placeholder('All stores'),
                    DatePicker::make('from_date')
                        ->label('From Date')
                        ->helperText('Only regenerate reports for sessions closed on or after this date')
                        ->placeholder('All dates'),
                    DatePicker::make('to_date')
                        ->label('To Date')
                        ->helperText('Only regenerate reports for sessions closed on or before this date')
                        ->placeholder('All dates'),
                    TextInput::make('limit')
                        ->label('Limit')
                        ->numeric()
                        ->helperText('Maximum number of sessions to process (leave empty for all)')
                        ->placeholder('No limit'),
                    Toggle::make('find_missing_data')
                        ->label('Find Missing Data')
                        ->helperText('Attempt to find and link missing charges, receipts, and events')
                        ->default(true),
                    Toggle::make('dry_run')
                        ->label('Dry Run')
                        ->helperText('Preview what would be done without making changes')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $action = new RegenerateZReports();
                    $options = [
                        'store_id' => $data['store_id'] ?? null,
                        'from_date' => $data['from_date'] ?? null,
                        'to_date' => $data['to_date'] ?? null,
                        'limit' => $data['limit'] ? (int) $data['limit'] : null,
                        'find_missing_data' => $data['find_missing_data'] ?? true,
                        'dry_run' => $data['dry_run'] ?? false,
                    ];

                    $stats = $action($options);

                    $message = "Processed {$stats['processed']} of {$stats['total_sessions']} sessions.\n";
                    $message .= "Regenerated: {$stats['regenerated']}\n";
                    
                    if ($stats['charges_found'] > 0 || $stats['receipts_found'] > 0 || $stats['events_found'] > 0) {
                        $message .= "Found: {$stats['charges_found']} charges, {$stats['receipts_found']} receipts, {$stats['events_found']} events\n";
                    }

                    if (!empty($stats['errors'])) {
                        $errorCount = count($stats['errors']);
                        $message .= "\nErrors: {$errorCount}";
                        if ($errorCount <= 5) {
                            $message .= "\n" . implode("\n", $stats['errors']);
                        } else {
                            $message .= "\n" . implode("\n", array_slice($stats['errors'], 0, 5));
                            $message .= "\n... and " . ($errorCount - 5) . " more";
                        }

                        Notification::make()
                            ->title('Regeneration completed with errors')
                            ->body($message)
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Regeneration completed')
                            ->body($message)
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
