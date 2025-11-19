<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'connectedCharges';

    protected static ?string $title = 'Charges';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer']))
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('amount', $direction);
                    }),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder(fn ($record) => $record->customer?->email ?? $record->stripe_customer_id ?? 'Unknown')
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'succeeded',
                        'warning' => 'pending',
                        'danger' => ['failed', 'refunded'],
                    ])
                    ->sortable(),

                TextColumn::make('charge_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'direct' ? 'info' : 'gray'),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_charge_id')
                    ->label('Charge ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'succeeded' => 'Succeeded',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\ConnectedCharges\ConnectedChargeResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
