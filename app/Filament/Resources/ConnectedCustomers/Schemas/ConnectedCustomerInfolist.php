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

                        TextEntry::make('phone')
                            ->label('Phone')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedPhone)
                            ->copyable(),

                        TextEntry::make('profile_image_url')
                            ->label('Profile Image URL')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedPhoto)
                            ->url(fn ($record) => $record->profile_image_url)
                            ->openUrlInNewTab()
                            ->copyable(),

                        TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),
                    ])
                    ->columns(2),

                Section::make('Address')
                    ->schema([
                        TextEntry::make('address.line1')
                            ->label('Address Line 1')
                            ->placeholder('-'),

                        TextEntry::make('address.line2')
                            ->label('Address Line 2')
                            ->placeholder('-'),

                        TextEntry::make('address.city')
                            ->label('City')
                            ->placeholder('-'),

                        TextEntry::make('address.state')
                            ->label('State / County')
                            ->placeholder('-'),

                        TextEntry::make('address.postal_code')
                            ->label('Postal Code')
                            ->placeholder('-'),

                        TextEntry::make('address.country')
                            ->label('Country')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->visible(fn ($record) => $record->address && is_array($record->address) && !empty(array_filter($record->address))),

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
