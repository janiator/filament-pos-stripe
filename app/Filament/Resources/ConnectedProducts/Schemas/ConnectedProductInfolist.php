<?php

namespace App\Filament\Resources\ConnectedProducts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
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

                Section::make('Product Images')
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('images')
                            ->label('Images')
                            ->collection('images')
                            ->conversion('')
                            ->limit(10)
                            ->visible(fn ($record) => $record->hasMedia('images')),
                    ])
                    ->collapsible()
                    ->collapsed(),

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

                Section::make('POS/Ecommerce Details')
                    ->schema([
                        IconEntry::make('shippable')
                            ->label('Shippable')
                            ->boolean()
                            ->icon(fn ($record) => $record->shippable
                                ? Heroicon::OutlinedTruck
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->shippable ? 'success' : 'gray')
                            ->visible(fn ($record) => $record->shippable !== null),

                        TextEntry::make('statement_descriptor')
                            ->label('Statement Descriptor')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->visible(fn ($record) => $record->statement_descriptor),

                        TextEntry::make('tax_code')
                            ->label('Tax Code')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalculator)
                            ->visible(fn ($record) => $record->tax_code),

                        TextEntry::make('unit_label')
                            ->label('Unit Label')
                            ->placeholder('-')
                            ->badge()
                            ->color('info')
                            ->visible(fn ($record) => $record->unit_label),

                        TextEntry::make('package_dimensions')
                            ->label('Package Dimensions')
                            ->formatStateUsing(fn ($state) => $state
                                ? collect($state)->map(fn ($value, $key) => ucfirst($key) . ': ' . $value)->join(', ')
                                : '-')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCube)
                            ->visible(fn ($record) => $record->package_dimensions),

                        TextEntry::make('default_price')
                            ->label('Default Price ID')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->visible(fn ($record) => $record->default_price),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),

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
