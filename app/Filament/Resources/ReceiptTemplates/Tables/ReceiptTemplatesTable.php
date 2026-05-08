<?php

namespace App\Filament\Resources\ReceiptTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReceiptTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template_type')
                    ->label(__('Type'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sales' => 'Sales Receipt',
                        'return' => 'Return Receipt',
                        'copy' => 'Copy Receipt',
                        'steb' => 'STEB Receipt',
                        'provisional' => 'Provisional Receipt',
                        'training' => 'Training Receipt',
                        'delivery' => 'Delivery Receipt',
                        'freeticket' => 'Free Ticket',
                        'ticket' => 'Booking Ticket',
                        default => ucfirst($state),
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->default('Global')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_custom')
                    ->label(__('Custom'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('version')
                    ->label(__('Version'))
                    ->sortable(),

                TextColumn::make('updater.name')
                    ->label(__('Last Updated By'))
                    ->default('System')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('Last Updated'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('template_type')
                    ->label(__('Template Type'))
                    ->options([
                        'sales' => 'Sales Receipt',
                        'return' => 'Return Receipt',
                        'copy' => 'Copy Receipt',
                        'steb' => 'STEB Receipt',
                        'provisional' => 'Provisional Receipt',
                        'training' => 'Training Receipt',
                        'delivery' => 'Delivery Receipt',
                        'freeticket' => 'Free Ticket',
                        'ticket' => 'Booking Ticket',
                    ]),

                SelectFilter::make('is_custom')
                    ->label(__('Custom'))
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
