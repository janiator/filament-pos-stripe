<?php

namespace App\Filament\Resources\ConnectedPaymentIntents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedPaymentIntentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $query->with(['store']);
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
                        'warning' => ['requires_payment_method', 'requires_confirmation', 'requires_action', 'requires_capture'],
                        'danger' => 'canceled',
                        'info' => 'processing',
                    ])
                    ->sortable(),

                TextColumn::make('capture_method')
                    ->label('Capture Method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'automatic' ? 'info' : 'gray')
                    ->sortable(),

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

                TextColumn::make('stripe_id')
                    ->label('Payment Intent ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('succeeded_at')
                    ->label('Succeeded At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
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
                        'requires_payment_method' => 'Requires Payment Method',
                        'requires_confirmation' => 'Requires Confirmation',
                        'requires_action' => 'Requires Action',
                        'processing' => 'Processing',
                        'requires_capture' => 'Requires Capture',
                        'succeeded' => 'Succeeded',
                        'canceled' => 'Canceled',
                    ]),

                SelectFilter::make('capture_method')
                    ->label('Capture Method')
                    ->options([
                        'automatic' => 'Automatic',
                        'manual' => 'Manual',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
