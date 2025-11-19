<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConnectedPaymentLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'connectedPaymentLinks';

    protected static ?string $title = 'Payment Links';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['price']))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('Unnamed')
                    ->wrap(),

                TextColumn::make('url')
                    ->label('URL')
                    ->copyable()
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab()
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedLink)
                    ->wrap(),

                TextColumn::make('link_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'direct' ? 'info' : 'gray'),

                IconColumn::make('active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('price.formatted_amount')
                    ->label('Price')
                    ->badge()
                    ->color('success')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('stripe_payment_link_id')
                    ->label('Payment Link ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\ConnectedPaymentLinks\ConnectedPaymentLinkResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
