<?php

namespace App\Filament\Resources\WebhookLogs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WebhookLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Information')
                    ->schema([
                        TextEntry::make('event_type')
                            ->label('Event Type')
                            ->badge()
                            ->color(fn ($record) => $record->processed ? 'success' : 'warning')
                            ->size(TextSize::Large),
                        
                        TextEntry::make('event_id')
                            ->label('Event ID')
                            ->copyable()
                            ->icon(\Filament\Support\Icons\Heroicon::OutlinedDocumentDuplicate),
                        
                        TextEntry::make('account_id')
                            ->label('Account ID')
                            ->copyable()
                            ->placeholder('N/A'),
                        
                        TextEntry::make('store.name')
                            ->label('Store')
                            ->url(fn ($record) => $record->store 
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null)
                            ->placeholder('No store found'),
                        
                        TextEntry::make('stripe_account_id')
                            ->label('Stripe Account ID')
                            ->copyable()
                            ->placeholder('N/A'),
                        
                        IconEntry::make('processed')
                            ->label('Processed')
                            ->boolean(),
                        
                        TextEntry::make('http_status_code')
                            ->label('HTTP Status Code')
                            ->badge()
                            ->color(fn ($record) => match(true) {
                                $record->http_status_code >= 500 => 'danger',
                                $record->http_status_code >= 400 => 'warning',
                                default => 'success',
                            }),
                    ])
                    ->columns(2),
                
                Section::make('Message')
                    ->schema([
                        TextEntry::make('message')
                            ->label('Message')
                            ->placeholder('No message')
                            ->wrap(),
                    ])
                    ->visible(fn ($record) => !empty($record->message)),
                
                Section::make('Warnings')
                    ->schema([
                        TextEntry::make('warnings')
                            ->label('')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('No warnings'),
                    ])
                    ->visible(fn ($record) => !empty($record->warnings)),
                
                Section::make('Errors')
                    ->schema([
                        TextEntry::make('errors')
                            ->label('')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->color('danger')
                            ->placeholder('No errors'),
                        
                        TextEntry::make('error_message')
                            ->label('Error Message')
                            ->color('danger')
                            ->placeholder('No error message')
                            ->wrap(),
                    ])
                    ->visible(fn ($record) => !empty($record->errors) || !empty($record->error_message)),
                
                Section::make('Request Data')
                    ->schema([
                        TextEntry::make('request_data')
                            ->label('')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->placeholder('No request data')
                            ->fontFamily('mono')
                            ->wrap(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->request_data)),
                
                Section::make('Response Data')
                    ->schema([
                        TextEntry::make('response_data')
                            ->label('')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->placeholder('No response data')
                            ->fontFamily('mono')
                            ->wrap(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->response_data)),
                
                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Received At')
                            ->dateTime(),
                        
                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
