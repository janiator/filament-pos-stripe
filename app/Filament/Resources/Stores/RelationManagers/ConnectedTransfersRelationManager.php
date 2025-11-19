<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedTransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'connectedTransfers';

    protected static ?string $title = 'Transfers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->badge()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(query: function ($query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy('amount', $direction);
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'info' => 'in_transit',
                        'danger' => ['failed', 'canceled'],
                    ])
                    ->sortable(),

                TextColumn::make('arrival_date')
                    ->label('Arrival Date')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('stripe_transfer_id')
                    ->label('Transfer ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'in_transit' => 'In Transit',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn ($record) => \App\Filament\Resources\ConnectedTransfers\ConnectedTransferResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
