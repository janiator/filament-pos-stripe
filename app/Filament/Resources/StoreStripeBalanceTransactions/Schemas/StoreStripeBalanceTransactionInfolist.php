<?php

namespace App\Filament\Resources\StoreStripeBalanceTransactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class StoreStripeBalanceTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.resources.store_stripe_balance_transaction.infolist.transaction'))
                    ->schema([
                        TextEntry::make('type')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.type'))
                            ->badge()
                            ->icon(Heroicon::OutlinedTag),

                        TextEntry::make('formatted_amount')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.amount'))
                            ->size(TextSize::Large)
                            ->badge()
                            ->icon(Heroicon::OutlinedCurrencyDollar),

                        TextEntry::make('formatted_fee')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.fee'))
                            ->badge()
                            ->color(fn ($record) => $record->fee > 0 ? 'warning' : 'gray'),

                        TextEntry::make('formatted_net')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.net'))
                            ->badge()
                            ->color('success'),

                        TextEntry::make('status')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.status'))
                            ->placeholder('-')
                            ->badge(),

                        TextEntry::make('description')
                            ->label(__('filament.resources.store_stripe_balance_transaction.infolist.description'))
                            ->placeholder('-')
                            ->wrap(),
                    ])
                    ->columns(2),

                Section::make(__('filament.resources.store_stripe_balance_transaction.infolist.links'))
                    ->schema([
                        TextEntry::make('stripe_charge_id')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.charge_id'))
                            ->copyable()
                            ->placeholder('-')
                            ->visible(fn ($record) => filled($record->stripe_charge_id)),

                        TextEntry::make('store.name')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.store'))
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),
                    ])
                    ->columns(2),

                Section::make(__('filament.resources.store_stripe_balance_transaction.infolist.technical'))
                    ->schema([
                        TextEntry::make('stripe_balance_transaction_id')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.txn_id'))
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_account_id')
                            ->label('Stripe account')
                            ->copyable()
                            ->icon(Heroicon::OutlinedIdentification),

                        TextEntry::make('available_on')
                            ->label(__('filament.resources.store_stripe_balance_transaction.infolist.available_on'))
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('reporting_category')
                            ->label(__('filament.resources.store_stripe_balance_transaction.infolist.reporting_category'))
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label(__('filament.resources.store_stripe_balance_transaction.columns.synced'))
                            ->dateTime()
                            ->icon(Heroicon::OutlinedArrowPath),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
