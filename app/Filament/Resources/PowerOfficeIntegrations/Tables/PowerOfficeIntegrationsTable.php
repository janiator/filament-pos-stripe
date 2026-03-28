<?php

namespace App\Filament\Resources\PowerOfficeIntegrations\Tables;

use App\Enums\PowerOfficeIntegrationStatus;
use App\Enums\PowerOfficeMappingBasis;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PowerOfficeIntegrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PowerOfficeIntegrationStatus $state): string => $state->label())
                    ->color(fn (PowerOfficeIntegrationStatus $state): string => match ($state) {
                        PowerOfficeIntegrationStatus::Connected => 'success',
                        PowerOfficeIntegrationStatus::PendingOnboarding => 'warning',
                        PowerOfficeIntegrationStatus::Error => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('environment')
                    ->badge(),
                TextColumn::make('mapping_basis')
                    ->label('Basis')
                    ->formatStateUsing(fn (PowerOfficeMappingBasis $state): string => $state->label()),
                TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PowerOfficeIntegrationStatus::cases())->mapWithKeys(
                        fn (PowerOfficeIntegrationStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
