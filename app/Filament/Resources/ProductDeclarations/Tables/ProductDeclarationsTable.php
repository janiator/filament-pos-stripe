<?php

namespace App\Filament\Resources\ProductDeclarations\Tables;

use App\Filament\Resources\ProductDeclarations\Pages\ListProductDeclarations;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductDeclarationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->label('Butikk')
                    ->searchable()
                    ->sortable()
                    ->visible(function () {
                        try {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            return $tenant && $tenant->slug === 'visivo-admin';
                        } catch (\Throwable $e) {
                            return false;
                        }
                    }),

                TextColumn::make('product_name')
                    ->label('Produktnavn')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vendor_name')
                    ->label('Leverandør')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('version')
                    ->label('Versjon')
                    ->sortable(),

                TextColumn::make('version_identification')
                    ->label('Versjonsidentifikasjon')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('declaration_date')
                    ->label('Dato')
                    ->date('d.m.Y')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Opprettet')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Oppdatert')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Aktiv',
                        false => 'Inaktiv',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
