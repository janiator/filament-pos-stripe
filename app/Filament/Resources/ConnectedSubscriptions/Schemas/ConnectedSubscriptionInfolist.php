<?php

namespace App\Filament\Resources\ConnectedSubscriptions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedSubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->size(TextSize::Large)
                            ->weight('bold'),

                        TextEntry::make('stripe_status')
                            ->label('Status')
                            ->badge()
                            ->colors([
                                'success' => ['active', 'trialing'],
                                'warning' => 'past_due',
                                'danger' => ['canceled', 'unpaid', 'incomplete'],
                                'info' => 'incomplete_expired',
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle),

                        TextEntry::make('formatted_amount')
                            ->label('Amount')
                            ->badge()
                            ->color('success')
                            ->placeholder('-')
                            ->formatStateUsing(function ($record) {
                                if ($record->connected_price_id && class_exists(\App\Models\ConnectedPrice::class)) {
                                    $price = \App\Models\ConnectedPrice::where('stripe_price_id', $record->connected_price_id)
                                        ->where('stripe_account_id', $record->stripe_account_id)
                                        ->first();
                                    if ($price && method_exists($price, 'getFormattedAmountAttribute')) {
                                        return $price->formatted_amount;
                                    }
                                }
                                return '-';
                            }),

                        TextEntry::make('quantity')
                            ->label('Quantity')
                            ->badge()
                            ->color('info')
                            ->default(1),
                    ])
                    ->columns(4),

                Section::make('Customer & Store')
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Customer')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedUser)
                            ->url(fn ($record) => $record->customer && class_exists(\App\Filament\Resources\ConnectedCustomers\ConnectedCustomerResource::class)
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

                Section::make('Subscription Details')
                    ->schema([
                        TextEntry::make('trial_ends_at')
                            ->label('Trial Ends At')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color(fn ($record) => $record->trial_ends_at && $record->trial_ends_at->isFuture() ? 'warning' : null),

                        TextEntry::make('ends_at')
                            ->label('Ends At')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color(fn ($record) => $record->ends_at && $record->ends_at->isPast() ? 'danger' : null),

                        TextEntry::make('current_period_start')
                            ->label('Current Period Start')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->visible(fn ($record) => $record->current_period_start),

                        TextEntry::make('current_period_end')
                            ->label('Current Period End')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->visible(fn ($record) => $record->current_period_end),

                        IconEntry::make('cancel_at_period_end')
                            ->label('Cancel at Period End')
                            ->boolean()
                            ->icon(fn ($record) => $record->cancel_at_period_end
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->cancel_at_period_end ? 'warning' : 'success')
                            ->visible(fn ($record) => isset($record->cancel_at_period_end)),
                    ])
                    ->columns(3),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_id')
                            ->label('Subscription ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('connected_price_id')
                            ->label('Price ID')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_customer_id')
                            ->label('Customer ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_account_id')
                            ->label('Account ID')
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
                    ->columns(3)
                    ->collapsible(),
            ]);
    }
}
