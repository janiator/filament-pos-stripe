<?php

namespace App\Filament\Resources\ConnectedPaymentMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectedPaymentMethodsTable
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
                TextColumn::make('card_display')
                    ->label('Payment Method')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->placeholder(fn ($record) => $record->customer?->email ?? $record->stripe_customer_id ?? 'Unknown')
                    ->description(fn ($record) => $record->customer?->email)
                    ->wrap(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('billing_details_name')
                    ->label('Billing Name')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stripe_payment_method_id')
                    ->label('Payment Method ID')
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
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'card' => 'Card',
                        'bank_account' => 'Bank Account',
                    ]),

                \Filament\Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default')
                    ->placeholder('All')
                    ->trueLabel('Default only')
                    ->falseLabel('Non-default only'),
            ])
            ->recordActions([
                ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
