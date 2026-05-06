<?php

namespace App\Filament\Resources\PosDevices\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class PosDeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Device Information')
                    ->schema([
                        TextEntry::make('device_name')
                            ->label(__('Device Name'))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),

                        TextEntry::make('device_identifier')
                            ->label(__('Device Identifier'))
                            ->copyable()
                            ->copyMessage('Device ID copied!'),

                        TextEntry::make('defaultPrinter.name')
                            ->label(__('Default Receipt Printer'))
                            ->placeholder(__('No default printer set'))
                            ->default('No default printer set'),

                        TextEntry::make('lastConnectedTerminalReader.label')
                            ->label(__('Last Connected Terminal'))
                            ->formatStateUsing(fn ($state, $record) => $record->lastConnectedTerminalLocation?->display_name
                                ? "{$record->lastConnectedTerminalLocation->display_name} / ".($state ?? $record->lastConnectedTerminalReader?->label ?? '—')
                                : ($state ?? $record->lastConnectedTerminalReader?->label ?? '—'))
                            ->placeholder(__('—')),

                        TextEntry::make('platform')
                            ->label(__('Platform'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'ios' => 'info',
                                'android' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                        TextEntry::make('device_status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'gray',
                                'maintenance' => 'warning',
                                'offline' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('cash_drawer_enabled')
                            ->label(__('Cash drawer enabled'))
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'warning'),

                        TextEntry::make('has_integrated_drawer')
                            ->label(__('Has integrated drawer'))
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                        TextEntry::make('booking_enabled')
                            ->label(__('Booking enabled'))
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                        TextEntry::make('auto_print_receipt')
                            ->label(__('Auto-print receipt'))
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ])
                    ->columns(2),

                Section::make('Device Details')
                    ->schema([
                        TextEntry::make('device_model')
                            ->label(__('Model')),

                        TextEntry::make('device_brand')
                            ->label(__('Brand')),

                        TextEntry::make('device_manufacturer')
                            ->label(__('Manufacturer')),

                        TextEntry::make('device_product')
                            ->label(__('Product')),

                        TextEntry::make('device_hardware')
                            ->label(__('Hardware')),

                        TextEntry::make('machine_identifier')
                            ->label(__('Machine Identifier')),

                        TextEntry::make('system_name')
                            ->label(__('System Name')),

                        TextEntry::make('system_version')
                            ->label(__('System Version')),

                        TextEntry::make('vendor_identifier')
                            ->label(__('Vendor Identifier')),

                        TextEntry::make('android_id')
                            ->label(__('Android ID')),

                        TextEntry::make('serial_number')
                            ->label(__('Serial Number')),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Status & Activity')
                    ->schema([
                        TextEntry::make('last_seen_at')
                            ->label(__('Last Seen'))
                            ->dateTime()
                            ->since()
                            ->color(fn ($state) => $state && $state->isBefore(now()->subHours(24)) ? 'danger' : null)
                            ->icon(fn ($state) => $state && $state->isBefore(now()->subHours(24)) ? 'heroicon-o-exclamation-triangle' : null),

                        TextEntry::make('created_at')
                            ->label(__('Registered'))
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label(__('Last Updated'))
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('Device Metadata')
                    ->schema([
                        KeyValueEntry::make('device_metadata')
                            ->label(__('Metadata')),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => ! empty($record->device_metadata)),

                Section::make('Store')
                    ->schema([
                        TextEntry::make('store.name')
                            ->label(__('Store Name')),

                        TextEntry::make('store.slug')
                            ->label(__('Store Slug')),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
