<?php

namespace App\Filament\Resources\ConnectedCharges\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedChargeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Charge Information')
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
                                'warning' => 'pending',
                                'danger' => ['failed', 'refunded'],
                                'info' => 'processing',
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle),

                        TextEntry::make('charge_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state))
                            ->color(fn ($state) => $state === 'direct' ? 'info' : 'gray')
                            ->icon(Heroicon::OutlinedCreditCard),

                        TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->placeholder('-')
                            ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : null)
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
                        IconEntry::make('paid')
                            ->label('Paid')
                            ->boolean()
                            ->icon(fn ($record) => $record->paid
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->paid ? 'success' : 'danger'),

                        IconEntry::make('captured')
                            ->label('Captured')
                            ->boolean()
                            ->icon(fn ($record) => $record->captured
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->captured ? 'success' : 'warning'),

                        IconEntry::make('refunded')
                            ->label('Refunded')
                            ->boolean()
                            ->icon(fn ($record) => $record->refunded
                                ? Heroicon::OutlinedXCircle
                                : Heroicon::OutlinedCheckCircle)
                            ->color(fn ($record) => $record->refunded ? 'danger' : 'success'),

                        TextEntry::make('formatted_amount_refunded')
                            ->label('Amount Refunded')
                            ->placeholder('$0.00')
                            ->badge()
                            ->color(fn ($record) => $record->amount_refunded > 0 ? 'warning' : 'gray')
                            ->visible(fn ($record) => $record->amount_refunded > 0),

                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color(fn ($record) => $record->paid_at && $record->paid_at->isPast() ? 'success' : null),
                    ])
                    ->columns(3),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_charge_id')
                            ->label('Charge ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_payment_intent_id')
                            ->label('Payment Intent ID')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('failure_code')
                            ->label('Failure Code')
                            ->placeholder('-')
                            ->badge()
                            ->color('danger')
                            ->visible(fn ($record) => $record->failure_code),

                        TextEntry::make('failure_message')
                            ->label('Failure Message')
                            ->placeholder('-')
                            ->wrap()
                            ->color('danger')
                            ->visible(fn ($record) => $record->failure_message),

                        TextEntry::make('application_fee_amount')
                            ->label('Application Fee')
                            ->formatStateUsing(fn ($state, $record) => $state 
                                ? number_format($state / 100, 2) . ' ' . strtoupper($record->currency)
                                : '-')
                            ->badge()
                            ->color('info')
                            ->visible(fn ($record) => $record->application_fee_amount),

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
