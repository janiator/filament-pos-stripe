<?php

namespace App\Filament\Resources\ConnectedCustomers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConnectedCustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('model')
                    ->searchable(),
                TextColumn::make('model_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('model_uuid'),
                TextColumn::make('stripe_customer_id')
                    ->searchable(),
                TextColumn::make('stripe_account_id')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
