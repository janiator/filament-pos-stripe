<?php

namespace App\Filament\Resources\ConnectedProducts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->size(TextSize::Large)
                            ->weight('bold'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText),

                        IconEntry::make('active')
                            ->label('Active')
                            ->boolean()
                            ->icon(fn ($record) => $record->active
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->active ? 'success' : 'danger'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Service')
                            ->color('gray')
                            ->visible(fn ($record) => $record->type),
                    ])
                    ->columns(2),

                Section::make('Store & Pricing')
                    ->schema([
                        TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),

                        TextEntry::make('prices_count')
                            ->label('Prices')
                            ->counts('prices')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedCurrencyDollar),
                    ])
                    ->columns(2),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_product_id')
                            ->label('Product ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_account_id')
                            ->label('Account ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('url')
                            ->label('URL')
                            ->copyable()
                            ->url(fn ($record) => $record->url)
                            ->openUrlInNewTab()
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->url),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }
}
