<?php

namespace App\Filament\Resources\ReceiptTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReceiptTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'sales' => 'Sales Receipt',
                        'return' => 'Return Receipt',
                        'copy' => 'Copy Receipt',
                        'steb' => 'STEB Receipt',
                        'provisional' => 'Provisional Receipt',
                        'training' => 'Training Receipt',
                        'delivery' => 'Delivery Receipt',
                        default => ucfirst($state),
                    })
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('store.name')
                    ->label('Store')
                    ->default('Global')
                    ->searchable()
                    ->sortable(),
                
                IconColumn::make('is_custom')
                    ->label('Custom')
                    ->boolean()
                    ->sortable(),
                
                TextColumn::make('version')
                    ->label('Version')
                    ->sortable(),
                
                TextColumn::make('updater.name')
                    ->label('Last Updated By')
                    ->default('System')
                    ->sortable(),
                
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('template_type')
                    ->label('Template Type')
                    ->options([
                        'sales' => 'Sales Receipt',
                        'return' => 'Return Receipt',
                        'copy' => 'Copy Receipt',
                        'steb' => 'STEB Receipt',
                        'provisional' => 'Provisional Receipt',
                        'training' => 'Training Receipt',
                        'delivery' => 'Delivery Receipt',
                    ]),
                
                SelectFilter::make('is_custom')
                    ->label('Custom')
                    ->options([
                        '1' => 'Custom',
                        '0' => 'Default',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('template_type')
            ->defaultSort('store_id');
    }
}
