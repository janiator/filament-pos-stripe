<?php

namespace App\Filament\Resources\TerminalReaders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TerminalReadersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('terminalLocation.display_name')
                    ->label(__('Location'))
                    ->sortable(),

                IconColumn::make('tap_to_pay')
                    ->label(__('Tap to Pay'))
                    ->boolean(),

                TextColumn::make('stripe_reader_id')
                    ->label(__('Stripe reader'))
                    ->copyable()
                    ->placeholder(__('-')),

                TextColumn::make('serial_number')
                    ->label(__('Serial number'))
                    ->copyable()
                    ->placeholder(__('-')),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->placeholder(__('-')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
