<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use App\Models\PaymentMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('posDevices'))
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('provider')
                    ->label('Provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'success',
                        'verifone' => 'info',
                        'cash' => 'warning',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('provider_method')
                    ->label('Provider Method')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('enabled')
                    ->label('Enabled')
                    ->sortable(),
                ToggleColumn::make('pos_suitable')
                    ->label('POS Suitable')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('minimum_amount_kroner')
                    ->label('Min amount (kr)')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state !== null ? $state.' kr' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('posDevices.device_name')
                    ->label('Devices')
                    ->formatStateUsing(function (PaymentMethod $record) {
                        $devices = $record->posDevices;
                        if ($devices->isEmpty()) {
                            return 'All devices';
                        }

                        return $devices->pluck('device_name')->join(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('saf_t_payment_code')
                    ->label('SAF-T Payment Code')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('Provider')
                    ->options([
                        'stripe' => 'Stripe',
                        'verifone' => 'Verifone',
                        'cash' => 'Cash',
                        'other' => 'Other',
                    ]),
                SelectFilter::make('enabled')
                    ->label('Status')
                    ->options([
                        '1' => 'Enabled',
                        '0' => 'Disabled',
                    ]),
                SelectFilter::make('pos_suitable')
                    ->label('POS Suitable')
                    ->options([
                        '1' => 'POS Suitable',
                        '0' => 'Online Only',
                    ]),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
