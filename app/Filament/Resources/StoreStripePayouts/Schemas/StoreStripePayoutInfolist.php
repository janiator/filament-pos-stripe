<?php

namespace App\Filament\Resources\StoreStripePayouts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class StoreStripePayoutInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.resources.store_stripe_payout.infolist.payout'))
                    ->schema([
                        TextEntry::make('formatted_amount')
                            ->label(__('filament.resources.store_stripe_payout.columns.amount'))
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success')
                            ->icon(Heroicon::OutlinedBanknotes),

                        TextEntry::make('status')
                            ->label(__('filament.resources.store_stripe_payout.columns.status'))
                            ->badge()
                            ->colors([
                                'success' => 'paid',
                                'warning' => ['pending', 'in_transit'],
                                'danger' => ['failed', 'canceled'],
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle),

                        TextEntry::make('arrival_date')
                            ->label(__('filament.resources.store_stripe_payout.columns.arrival_date'))
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('method')
                            ->label(__('filament.resources.store_stripe_payout.columns.method'))
                            ->placeholder('-'),

                        TextEntry::make('statement_descriptor')
                            ->label(__('filament.resources.store_stripe_payout.infolist.statement_descriptor'))
                            ->placeholder('-')
                            ->wrap(),
                    ])
                    ->columns(2),

                Section::make(__('filament.resources.store_stripe_payout.infolist.store'))
                    ->schema([
                        TextEntry::make('store.name')
                            ->label(__('filament.resources.store_stripe_payout.columns.store'))
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),
                    ])
                    ->columns(2),

                Section::make(__('filament.resources.store_stripe_payout.infolist.technical'))
                    ->schema([
                        TextEntry::make('stripe_payout_id')
                            ->label(__('filament.resources.store_stripe_payout.columns.payout_id'))
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_account_id')
                            ->label('Stripe account')
                            ->copyable()
                            ->icon(Heroicon::OutlinedIdentification),

                        TextEntry::make('failure_code')
                            ->label(__('filament.resources.store_stripe_payout.infolist.failure_code'))
                            ->visible(fn ($record) => filled($record->failure_code)),

                        TextEntry::make('failure_message')
                            ->label(__('filament.resources.store_stripe_payout.infolist.failure_message'))
                            ->visible(fn ($record) => filled($record->failure_message))
                            ->wrap(),

                        TextEntry::make('updated_at')
                            ->label(__('filament.resources.store_stripe_payout.columns.synced'))
                            ->dateTime()
                            ->icon(Heroicon::OutlinedArrowPath),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
