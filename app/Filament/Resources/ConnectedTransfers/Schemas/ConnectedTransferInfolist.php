<?php

namespace App\Filament\Resources\ConnectedTransfers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ConnectedTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transfer Information')
                    ->schema([
                        TextEntry::make('formatted_amount')
                            ->label(__('Amount'))
                            ->placeholder(__('-'))
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->size(TextSize::Large)
                            ->badge()
                            ->color('success'),

                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->colors([
                                'success' => 'paid',
                                'warning' => 'pending',
                                'info' => 'in_transit',
                                'danger' => ['failed', 'canceled'],
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle),

                        TextEntry::make('arrival_date')
                            ->label(__('Arrival Date'))
                            ->dateTime()
                            ->placeholder(__('-'))
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color(fn ($record) => $record->arrival_date && $record->arrival_date->isPast() ? 'success' : 'warning'),

                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder(__('-'))
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText),
                    ])
                    ->columns(2),

                Section::make('Store & Destination')
                    ->schema([
                        TextEntry::make('store.name')
                            ->label(__('Store'))
                            ->placeholder(__('-'))
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),

                        TextEntry::make('destination')
                            ->label(__('Destination'))
                            ->placeholder(__('-'))
                            ->copyable()
                            ->icon(Heroicon::OutlinedArrowRightCircle),
                    ])
                    ->columns(2),

                Section::make('Transfer Details')
                    ->schema([
                        TextEntry::make('formatted_reversed_amount')
                            ->label(__('Reversed Amount'))
                            ->placeholder(__('$0.00'))
                            ->badge()
                            ->color(fn ($record) => $record->reversed_amount > 0 ? 'danger' : 'gray')
                            ->visible(fn ($record) => $record->reversed_amount > 0),

                        TextEntry::make('formatted_net_amount')
                            ->label(__('Net Amount'))
                            ->badge()
                            ->color('success')
                            ->visible(fn ($record) => $record->reversed_amount > 0),

                        TextEntry::make('stripe_charge_id')
                            ->label(__('Source Charge ID'))
                            ->copyable()
                            ->placeholder(__('-'))
                            ->icon(Heroicon::OutlinedHashtag)
                            ->visible(fn ($record) => $record->stripe_charge_id),
                    ])
                    ->columns(3),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_transfer_id')
                            ->label(__('Transfer ID'))
                            ->copyable()
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('created_at')
                            ->label(__('Created'))
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),

                        TextEntry::make('updated_at')
                            ->label(__('Updated'))
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
