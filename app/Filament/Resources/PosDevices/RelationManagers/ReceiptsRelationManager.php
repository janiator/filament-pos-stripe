<?php

namespace App\Filament\Resources\PosDevices\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'receipts';

    protected static ?string $title = 'Receipts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('receipt_number')
            ->columns([
                Tables\Columns\TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sales' => 'success',
                        'return' => 'warning',
                        'correction' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('posSession.session_number')
                    ->label('Session')
                    ->sortable(),
                Tables\Columns\TextColumn::make('printed')
                    ->label('Printed')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reprint_count')
                    ->label('Reprints')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('receipt_type')
                    ->label('Type')
                    ->options([
                        'sales' => 'Sales',
                        'return' => 'Return',
                        'correction' => 'Correction',
                    ]),
                Tables\Filters\TernaryFilter::make('printed')
                    ->label('Printed')
                    ->placeholder('All')
                    ->trueLabel('Printed only')
                    ->falseLabel('Not printed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
