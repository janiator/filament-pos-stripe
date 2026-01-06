<?php

namespace App\Filament\Resources\WebhookLogs\Tables;

use App\Models\Store;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\IconSize;

class WebhookLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')
                    ->label('Event Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->processed ? 'success' : 'warning'),
                
                TextColumn::make('event_id')
                    ->label('Event ID')
                    ->searchable()
                    ->copyable()
                    ->limit(20),
                
                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->store 
                        ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                        : null),
                
                TextColumn::make('stripe_account_id')
                    ->label('Stripe Account')
                    ->searchable()
                    ->copyable()
                    ->limit(20),
                
                TextColumn::make('message')
                    ->label('Message')
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn ($record) => $record->message),
                
                IconColumn::make('processed')
                    ->label('Processed')
                    ->boolean()
                    ->size(IconSize::Small),
                
                TextColumn::make('http_status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => match(true) {
                        $record->http_status_code >= 500 => 'danger',
                        $record->http_status_code >= 400 => 'warning',
                        default => 'success',
                    }),
                
                TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->description(fn ($record) => $record->created_at->format('Y-m-d H:i:s')),
            ])
            ->filters([
                SelectFilter::make('processed')
                    ->label('Processed')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
                
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options(function () {
                        return \App\Models\WebhookLog::query()
                            ->distinct()
                            ->pluck('event_type', 'event_type')
                            ->toArray();
                    })
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
