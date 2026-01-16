<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\Store;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class StoreInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Store Information')
                    ->schema([
                        TextEntry::make('name')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->size(TextSize::Large)
                            ->weight('bold'),

                        TextEntry::make('email')
                            ->label('Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable(),

                        TextEntry::make('z_report_email')
                            ->label('Z-Report Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->copyable()
                            ->placeholder('Not configured'),

                        TextEntry::make('commission_type')
                            ->label('Commission Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state))
                            ->colors([
                                'success' => 'percentage',
                                'warning' => 'fixed',
                            ])
                            ->icon(Heroicon::OutlinedCurrencyDollar),

                        TextEntry::make('commission_rate')
                            ->label('Commission Rate')
                            ->formatStateUsing(function (Store $record): string {
                                if ($record->commission_type === 'percentage') {
                                    return "{$record->commission_rate}%";
                                }

                                return number_format($record->commission_rate / 100, 2);
                            })
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedCurrencyDollar),
                    ])
                    ->columns(4),

                Section::make('Stripe Connection')
                    ->schema([
                        TextEntry::make('stripe_account_id')
                            ->label('Stripe Account ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag)
                            ->placeholder('-')
                            ->color(fn (Store $record) => $record->stripe_account_id ? 'success' : 'danger'),

                        TextEntry::make('connected_customers_count')
                            ->label('Customers')
                            ->counts('connectedCustomers')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedUser)
                            ->visible(fn () => class_exists(\App\Models\ConnectedCustomer::class)),

                        TextEntry::make('connected_subscriptions_count')
                            ->label('Subscriptions')
                            ->counts('connectedSubscriptions')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->visible(fn () => class_exists(\App\Models\ConnectedSubscription::class)),

                        TextEntry::make('connected_products_count')
                            ->label('Products')
                            ->counts('connectedProducts')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->visible(fn () => class_exists(\App\Models\ConnectedProduct::class)),
                    ])
                    ->columns(4),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar)
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar)
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
