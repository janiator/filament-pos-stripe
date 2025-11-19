<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedPaymentMethodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Method Information')
                    ->schema([
                        TextEntry::make('card_display')
                            ->label('Payment Method')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->size(TextSize::Large)
                            ->weight('bold'),

                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state))
                            ->color('gray')
                            ->icon(Heroicon::OutlinedCreditCard),

                        IconEntry::make('is_default')
                            ->label('Default Payment Method')
                            ->boolean()
                            ->icon(fn ($record) => $record->is_default
                                ? Heroicon::OutlinedStar
                                : Heroicon::OutlinedStar)
                            ->color(fn ($record) => $record->is_default ? 'warning' : 'gray'),
                    ])
                    ->columns(3),

                Section::make('Customer & Store')
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedUser)
                            ->url(fn ($record) => class_exists(\App\Models\ConnectedCustomer::class) && $record->customer
                                ? \App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource::getUrl('view', ['record' => $record->customer])
                                : null),

                        TextEntry::make('customer.email')
                            ->label('Customer Email')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->visible(fn ($record) => $record->customer && $record->customer->email),

                        TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),
                    ])
                    ->columns(3),

                Section::make('Card Details')
                    ->schema([
                        TextEntry::make('card_brand')
                            ->label('Brand')
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : null)
                            ->badge()
                            ->color('gray')
                            ->visible(fn ($record) => $record->type === 'card'),

                        TextEntry::make('card_last4')
                            ->label('Last 4 Digits')
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state) => $state ? "•••• {$state}" : null)
                            ->visible(fn ($record) => $record->type === 'card'),

                        TextEntry::make('card_exp_month')
                            ->label('Expiration')
                            ->formatStateUsing(fn ($state, $record) => ($state && $record->card_exp_year)
                                ? "{$state}/{$record->card_exp_year}"
                                : '-')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->type === 'card'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->type === 'card'),

                Section::make('Billing Details')
                    ->schema([
                        TextEntry::make('billing_details_name')
                            ->label('Name')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedUser),

                        TextEntry::make('billing_details_email')
                            ->label('Email')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedEnvelope),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_payment_method_id')
                            ->label('Payment Method ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_customer_id')
                            ->label('Customer ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

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
