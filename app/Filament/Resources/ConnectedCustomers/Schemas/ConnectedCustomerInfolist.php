<?php

namespace App\Filament\Resources\ConnectedCustomers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedCustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedUser)
                            ->size(TextSize::Large)
                            ->weight('bold'),

                        TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable(),

                        TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),
                    ])
                    ->columns(3),

                Section::make('Stripe Details')
                    ->schema([
                        TextEntry::make('stripe_customer_id')
                            ->label('Customer ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_account_id')
                            ->label('Account ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Model Mapping')
                    ->schema([
                        TextEntry::make('model')
                            ->label('Model')
                            ->placeholder('-'),

                        TextEntry::make('model_id')
                            ->label('Model ID')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->model_id),

                        TextEntry::make('model_uuid')
                            ->label('Model UUID')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->model_uuid),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->visible(fn ($record) => $record->model),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
