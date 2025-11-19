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
                                'success' => 'paid',
                                'warning' => 'pending',
                                'info' => 'in_transit',
                                'danger' => ['failed', 'canceled'],
                            ])
                            ->icon(Heroicon::OutlinedCheckCircle),

                        TextEntry::make('arrival_date')
                            ->label('Arrival Date')
                            ->dateTime()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedCalendar)
                            ->color(fn ($record) => $record->arrival_date && $record->arrival_date->isPast() ? 'success' : 'warning'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->wrap()
                            ->icon(Heroicon::OutlinedDocumentText),
                    ])
                    ->columns(2),

                Section::make('Store & Destination')
                    ->schema([
                        TextEntry::make('store.name')
                            ->label('Store')
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->url(fn ($record) => $record->store
                                ? \App\Filament\Resources\Stores\StoreResource::getUrl('view', ['record' => $record->store])
                                : null),

                        TextEntry::make('destination')
                            ->label('Destination')
                            ->placeholder('-')
                            ->copyable()
                            ->icon(Heroicon::OutlinedArrowRightCircle),
                    ])
                    ->columns(2),

                Section::make('Transfer Details')
                    ->schema([
                        TextEntry::make('formatted_reversed_amount')
                            ->label('Reversed Amount')
                            ->placeholder('$0.00')
                            ->badge()
                            ->color(fn ($record) => $record->reversed_amount > 0 ? 'danger' : 'gray')
                            ->visible(fn ($record) => $record->reversed_amount > 0),

                        TextEntry::make('formatted_net_amount')
                            ->label('Net Amount')
                            ->badge()
                            ->color('success')
                            ->visible(fn ($record) => $record->reversed_amount > 0),

                        TextEntry::make('stripe_charge_id')
                            ->label('Source Charge ID')
                            ->copyable()
                            ->placeholder('-')
                            ->icon(Heroicon::OutlinedHashtag)
                            ->visible(fn ($record) => $record->stripe_charge_id),
                    ])
                    ->columns(3),

                Section::make('Technical Details')
                    ->schema([
                        TextEntry::make('stripe_transfer_id')
                            ->label('Transfer ID')
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
