<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedPaymentIntentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Intent Information')
                    ->schema([
                        TextEntry::make('formatted_amount')
                            ->label('Amount')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->colors([
                                'success' => 'succeeded',
                                'warning' => ['requires_payment_method', 'requires_confirmation', 'requires_action', 'requires_capture'],
                                'danger' => 'canceled',
                                'info' => 'processing',
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle),

                        TextEntry::make('capture_method')
                            ->label('Capture Method')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state))
                            ->color(fn ($state) => $state === 'automatic' ? 'info' : 'gray')
                            ->icon(Heroicon::OutlinedCreditCard),

                        TextEntry::make('confirmation_method')
                            ->label('Confirmation Method')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state))
                            ->color('gray')
                            ->icon(Heroicon::OutlinedCreditCard),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText),
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

                Section::make('Payment Details')
                    ->schema([
                        TextEntry::make('receipt_email')
                            ->label('Receipt Email')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable(),

                        TextEntry::make('statement_descriptor')
                            ->label('Statement Descriptor')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedDocumentText),

                        TextEntry::make('statement_descriptor_suffix')
                            ->label('Statement Descriptor Suffix')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedDocumentText),

                        TextEntry::make('succeeded_at')
                            ->label('Succeeded At')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color(fn ($record) => $record->succeeded_at && $record->succeeded_at->isPast() ? 'success' : null)
                            ->visible(fn ($record) => $record->succeeded_at),

                        TextEntry::make('canceled_at')
                            ->label('Canceled At')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color('danger')
                            ->visible(fn ($record) => $record->canceled_at),

                        TextEntry::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->placeholder('-')
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record) => $record->cancellation_reason),
                    ])
                    ->columns(3),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_id')
                            ->label('Payment Intent ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_payment_method_id')
                            ->label('Payment Method ID')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('client_secret')
                            ->label('Client Secret')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedKey)
                            ->visible(fn ($record) => $record->client_secret),

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
