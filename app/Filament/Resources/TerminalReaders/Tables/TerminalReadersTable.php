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
                    ->label('Location')
                    ->sortable(),

                IconColumn::make('tap_to_pay')
                    ->label('Tap to Pay')
                    ->boolean(),

                TextColumn::make('stripe_reader_id')
                    ->label('Stripe reader')
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('serial_number')
                    ->label('Serial number')
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->placeholder('-'),

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
