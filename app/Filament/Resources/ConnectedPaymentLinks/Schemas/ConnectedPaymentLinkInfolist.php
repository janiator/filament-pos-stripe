<?php

namespace App\Filament\Resources\ConnectedPaymentLinks\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedPaymentLinkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Link Information')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->placeholder('Unnamed')
                            ->icon(Heroicon::OutlinedLink)
                            ->size(TextSize::Large)
                            ->weight('bold'),

                        TextEntry::make('url')
                            ->label('URL')
                            ->copyable()
                            ->url(fn ($record) => $record->url)
                            ->openUrlInNewTab()
                            ->icon(Heroicon::OutlinedLink)
                            ->color('info')
                            ->wrap(),

                        TextEntry::make('link_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucfirst($state))
                            ->color(fn ($state) => $state === 'direct' ? 'info' : 'gray')
                            ->icon(Heroicon::OutlinedCreditCard),

                        IconEntry::make('active')
                            ->label('Active')
                            ->boolean()
                            ->icon(fn ($record) => $record->active
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle)
                            ->color(fn ($record) => $record->active ? 'success' : 'danger'),
                    ])
                    ->columns(2),

                Section::make('Price & Store')
                    ->schema([
                        TextEntry::make('price.formatted_amount')
                            ->label('Price')
                            ->badge()
                            ->color('success')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->visible(fn ($record) => $record->price),

                        TextEntry::make('price.recurring_description')
                            ->label('Billing Interval')
                            ->badge()
                            ->color('info')
                            ->placeholder('-')
                            ->visible(fn ($record) => $record->price && $record->price->recurring_description),

                        TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),
                    ])
                    ->columns(3),

                Section::make('Application Fee')
                    ->schema([
                        TextEntry::make('application_fee_percent')
                            ->label('Fee Percentage')
                            ->formatStateUsing(fn ($state) => $state ? "{$state}%" : '-')
                            ->badge()
                            ->color('info')
                            ->visible(fn ($record) => $record->application_fee_percent),

                        TextEntry::make('application_fee_amount')
                            ->label('Fee Amount')
                            ->formatStateUsing(function ($state, $record) {
                                if (!$state) return '-';
                                // Get currency from price if available
                                $currency = $record->price?->currency ?? 'USD';
                                return number_format($state / 100, 2) . ' ' . strtoupper($currency);
                            })
                            ->badge()
                            ->color('info')
                            ->visible(fn ($record) => $record->application_fee_amount),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->application_fee_percent || $record->application_fee_amount),

                Section::make('Redirect & Description')
                    ->schema([
                        TextEntry::make('after_completion_redirect_url')
                            ->label('Redirect URL')
                            ->copyable()
                            ->url(fn ($record) => $record->after_completion_redirect_url)
                            ->openUrlInNewTab()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->visible(fn ($record) => $record->after_completion_redirect_url),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_payment_link_id')
                            ->label('Payment Link ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('stripe_price_id')
                            ->label('Price ID')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->visible(fn ($record) => $record->stripe_price_id),

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
