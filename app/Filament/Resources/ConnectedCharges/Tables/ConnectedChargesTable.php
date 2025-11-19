<?php

namespace App\Filament\Resources\ConnectedCharges\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedChargesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['store']);
                // Note: Customer relationship will be loaded but may not be filtered by account_id
                // This is acceptable as the relationship is defined to match on customer_id only
                if (class_exists(\App\Models\ConnectedCustomer::class)) {
                    $query->with(['customer']);
                }
            })
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
                    ->sortable()
                    ->placeholder(fn ($record) => $record->customer?->email ?? $record->stripe_customer_id ?? 'Unknown')
                    ->description(fn ($record) => $record->customer?->email)
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'succeeded',
                        'warning' => 'pending',
                        'danger' => ['failed', 'refunded'],
                        'info' => 'processing',
                    ])
                    ->sortable(),

                TextColumn::make('charge_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'direct' ? 'info' : 'gray')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '-')
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_charge_id')
                    ->label('Charge ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
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
                        'processing' => 'Processing',
                    ]),

                SelectFilter::make('charge_type')
                    ->label('Type')
                    ->options([
                        'direct' => 'Direct',
                        'destination' => 'Destination',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                // Charges are typically created via Payment Intents, not manually
                // EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
