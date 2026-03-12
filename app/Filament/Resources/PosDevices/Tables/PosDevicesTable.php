<?php

namespace App\Filament\Resources\PosDevices\Tables;

use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PosDevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount(['posSessions' => fn ($query) => $query->where('status', 'open')])
                ->with(['terminalLocation', 'lastConnectedTerminalLocation', 'lastConnectedTerminalReader']))
            ->columns([
                TextColumn::make('device_name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ios' => 'info',
                        'android' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->sortable(),

                TextColumn::make('device_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'maintenance' => 'warning',
                        'offline' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('device_model')
                    ->label('Model')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('system_version')
                    ->label('OS Version')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->color(fn ($state) => $state && $state->isBefore(now()->subHours(24)) ? 'danger' : null)
                    ->icon(fn ($state) => $state && $state->isBefore(now()->subHours(24)) ? 'heroicon-o-exclamation-triangle' : null),

                TextColumn::make('terminalLocation.display_name')
                    ->label('Terminal Location')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('lastConnectedTerminalReader.label')
                    ->label('Last Terminal')
                    ->formatStateUsing(function ($record) {
                        $loc = $record->lastConnectedTerminalLocation?->display_name;
                        $reader = $record->lastConnectedTerminalReader?->label;
                        if (! $loc && ! $reader) {
                            return '—';
                        }

                        return $reader
                            ? ($loc ? "{$loc} / {$reader}" : $reader)
                            : ($loc ?? '—');
                    })
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('pos_sessions_count')
                    ->label('Open Sessions')
                    ->badge()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('device_identifier')
                    ->label('Device ID')
                    ->copyable()
                    ->copyMessage('Device ID copied!')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'ios' => 'iOS',
                        'android' => 'Android',
                    ]),

                \Filament\Tables\Filters\SelectFilter::make('device_status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'maintenance' => 'Maintenance',
                        'offline' => 'Offline',
                    ]),

                \Filament\Tables\Filters\Filter::make('last_seen_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('last_seen_from')
                            ->label('Last Seen From'),
                        \Filament\Forms\Components\DatePicker::make('last_seen_until')
                            ->label('Last Seen Until'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['last_seen_from'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('last_seen_at', '>=', $date),
                            )
                            ->when(
                                $data['last_seen_until'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('last_seen_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }
}
