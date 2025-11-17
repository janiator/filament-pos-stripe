<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Models\Store;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('commission_type')
                    ->label('Type')
                    ->badge()
                    ->colors([
                        'success' => 'percentage',
                        'warning' => 'fixed',
                    ])
                    ->sortable(),

                TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->formatStateUsing(function (Store $record): string {
                        if ($record->commission_type === 'percentage') {
                            return "{$record->commission_rate}%";
                        }

                        return number_format($record->commission_rate / 100, 2);
                    })
                    ->sortable(),

                TextColumn::make('stripe_account_id')
                    ->label('Stripe account')
                    ->searchable()
                    ->copyable()
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
                TernaryFilter::make('connected_to_stripe')
                    ->label('Connected to Stripe')
                    ->trueLabel('Connected')
                    ->falseLabel('Not connected')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('stripe_account_id'),
                        false: fn ($query) => $query->whereNull('stripe_account_id'),
                        blank: fn ($query) => $query,
                    ),
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
