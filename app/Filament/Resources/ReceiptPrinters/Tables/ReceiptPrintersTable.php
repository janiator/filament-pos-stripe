<?php

namespace App\Filament\Resources\ReceiptPrinters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReceiptPrintersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('printer_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'epson' => 'Epson ePOS',
                        'star' => 'Star Micronics',
                        'other' => 'Other',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'epson' => 'success',
                        'star' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('printer_model')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', ucwords($state, '_')) : 'N/A')
                    ->sortable(),
                
                TextColumn::make('paper_width')
                    ->label('Paper')
                    ->formatStateUsing(fn (string $state): string => "{$state}mm")
                    ->sortable(),
                
                TextColumn::make('connection_type')
                    ->label('Connection')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'network' => 'Network',
                        'usb' => 'USB',
                        'bluetooth' => 'Bluetooth',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->sortable(),
                
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->visible(function ($livewire) {
                        if (!method_exists($livewire, 'getTable')) {
                            return true; // Show by default if table not available
                        }
                        $table = $livewire->getTable();
                        $filterState = $table->getFilter('connection_type')?->getState();
                        return $filterState === 'network' || $filterState === null;
                    })
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('posDevice.device_name')
                    ->label('POS Device')
                    ->default('Not linked')
                    ->sortable(),
                
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Never'),
                
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('printer_type')
                    ->label('Printer Type')
                    ->options([
                        'epson' => 'Epson ePOS',
                        'star' => 'Star Micronics',
                        'other' => 'Other',
                    ]),
                
                SelectFilter::make('connection_type')
                    ->label('Connection Type')
                    ->options([
                        'network' => 'Network',
                        'usb' => 'USB',
                        'bluetooth' => 'Bluetooth',
                    ]),
                
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All printers')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
