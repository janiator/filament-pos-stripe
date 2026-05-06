<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class VerifoneTerminalPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'verifoneTerminalPayments';

    protected static ?string $title = 'Verifone Terminal Payments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('service_id')
                    ->label(__('Service ID'))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('terminal.terminal_identifier')
                    ->label(__('POIID'))
                    ->placeholder(__('-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'succeeded',
                        'danger' => ['failed', 'canceled'],
                        'warning' => ['pending', 'in_progress'],
                        'gray' => ['unknown'],
                    ]),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('Amount'))
                    ->formatStateUsing(function ($state, $record): string {
                        $currency = strtoupper((string) ($record->currency ?? 'NOK'));
                        $amount = number_format(((int) $state) / 100, 2, '.', '');

                        return "{$amount} {$currency}";
                    }),

                Tables\Columns\TextColumn::make('provider_payment_reference')
                    ->label(__('Payment reference'))
                    ->placeholder(__('-'))
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('provider_transaction_id')
                    ->label(__('Transaction ID'))
                    ->placeholder(__('-'))
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label(__('Completed'))
                    ->dateTime()
                    ->placeholder(__('-'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('failed_at')
                    ->label(__('Failed'))
                    ->dateTime()
                    ->placeholder(__('-'))
                    ->toggleable(),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
