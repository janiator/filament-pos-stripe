<?php

namespace App\Filament\Resources\PowerOfficeAccountMappings\Tables;

use App\Enums\PowerOfficeMappingBasis;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PowerOfficeAccountMappingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('basis_type')
                    ->label(__('Basis'))
                    ->formatStateUsing(fn (PowerOfficeMappingBasis $state): string => $state->label()),
                TextColumn::make('basis_key')
                    ->searchable(),
                TextColumn::make('basis_label')
                    ->placeholder(__('—')),
                TextColumn::make('sales_account_no'),
                TextColumn::make('vat_account_no')
                    ->placeholder(__('—')),
                TextColumn::make('tips_account_no')
                    ->placeholder(__('—')),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
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
