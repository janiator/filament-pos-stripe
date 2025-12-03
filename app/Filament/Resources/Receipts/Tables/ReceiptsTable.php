<?php

namespace App\Filament\Resources\Receipts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\Receipt;

class ReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('receipt_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sales' => 'success',
                        'return' => 'danger',
                        'copy' => 'gray',
                        'steb' => 'info',
                        'provisional' => 'warning',
                        'training' => 'warning',
                        'delivery' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable(),
                TextColumn::make('posSession.session_number')
                    ->label('Session')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cashier')
                    ->sortable(),
                TextColumn::make('charge_id')
                    ->label('Charge')
                    ->formatStateUsing(function ($state, $record) {
                        $charge = $record->charge;
                        if (!$charge) {
                            return 'N/A';
                        }
                        // Handle null stripe_charge_id (cash payments)
                        if ($charge->stripe_charge_id) {
                            return $charge->stripe_charge_id;
                        }
                        return 'Cash #' . $charge->id;
                    })
                    ->limit(30),
                IconColumn::make('printed')
                    ->label('Printed')
                    ->boolean(),
                TextColumn::make('reprint_count')
                    ->label('Reprints')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('receipt_type')
                    ->label('Receipt Type')
                    ->options([
                        'sales' => 'Sales',
                        'return' => 'Return',
                        'copy' => 'Copy',
                        'steb' => 'STEB',
                        'provisional' => 'Provisional',
                        'training' => 'Training',
                        'delivery' => 'Delivery',
                    ]),
                SelectFilter::make('printed')
                    ->label('Printed')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // No bulk actions for receipts
                ]),
            ]);
    }
}
