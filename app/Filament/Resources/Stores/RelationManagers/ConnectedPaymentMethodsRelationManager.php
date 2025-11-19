<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConnectedPaymentMethodsRelationManager extends RelationManager
{
    protected static string $relationship = 'connectedPaymentMethods';

    protected static ?string $title = 'Payment Methods';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer']))
            ->columns([
                TextColumn::make('card_display')
                    ->label('Payment Method')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder(fn ($record) => $record->customer?->email ?? $record->stripe_customer_id ?? 'Unknown')
                    ->wrap(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color('gray'),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                TextColumn::make('stripe_payment_method_id')
                    ->label('Payment Method ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\ConnectedPaymentMethods\ConnectedPaymentMethodResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
