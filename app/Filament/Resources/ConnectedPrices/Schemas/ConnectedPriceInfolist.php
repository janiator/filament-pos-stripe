<?php

namespace App\Filament\Resources\ConnectedPrices\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedPriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price Information')
                    ->schema([
                        TextEntry::make('formatted_amount')
                            ->label('Amount')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success')
                            ->weight('bold'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state))
                            ->colors([
                                'success' => 'recurring',
                                'info' => 'one_time',
                            ])
                            ->icon(Heroicon::OutlinedCreditCard),

                        TextEntry::make('recurring_description')
                            ->label('Billing Interval')
                            ->badge()
                            ->color('info')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->visible(fn ($record) => $record->type === 'recurring'),

                        TextEntry::make('currency')
                            ->label('Currency')
                            ->badge()
                            ->formatStateUsing(fn ($state) => strtoupper($state))
                            ->color('gray'),

                        IconEntry::make('active')
                            ->label('Active')
                            ->boolean()
                            ->icon(fn ($record) => $record->active
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->active ? 'success' : 'danger'),
                    ])
                    ->columns(3),

                Section::make('Product & Store')
                    ->schema([
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->url(fn ($record) => $record->product && class_exists(\App\Filament\Resources\ConnectedProducts\ConnectedProductResource::class)
                                ? \App\Filament\Resources\ConnectedProducts\ConnectedProductResource::getUrl('view', ['record' => $record->product])
                                : null),

                        TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),
                    ])
                    ->columns(2),

                Section::make('Price Details')
                    ->schema([
                        TextEntry::make('nickname')
                            ->label('Nickname')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->nickname),

                        TextEntry::make('billing_scheme')
                            ->label('Billing Scheme')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                            ->color('gray')
                            ->visible(fn ($record) => $record->billing_scheme),

                        TextEntry::make('tiers_mode')
                            ->label('Tiers Mode')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                            ->color('gray')
                            ->visible(fn ($record) => $record->tiers_mode),

                        TextEntry::make('recurring_usage_type')
                            ->label('Usage Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                            ->color('gray')
                            ->visible(fn ($record) => $record->recurring_usage_type),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_price_id')
                            ->label('Price ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_product_id')
                            ->label('Product ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag)
                            ->visible(fn ($record) => $record->stripe_product_id),

                        TextEntry::make('stripe_account_id')
                            ->label('Account ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('unit_amount')
                            ->label('Unit Amount (cents)')
                            ->numeric()
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->visible(fn ($record) => $record->unit_amount),

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
