<?php

namespace App\Filament\Resources\PosDevices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
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
                            ->label('Device Name')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),
                        
                        TextEntry::make('device_identifier')
                            ->label('Device Identifier')
                            ->copyable()
                            ->copyMessage('Device ID copied!'),
                        
                        TextEntry::make('platform')
                            ->label('Platform')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'ios' => 'info',
                                'android' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                        
                        TextEntry::make('device_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'gray',
                                'maintenance' => 'warning',
                                'offline' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(2),
                
                Section::make('Device Details')
                    ->schema([
                        TextEntry::make('device_model')
                            ->label('Model'),
                        
                        TextEntry::make('device_brand')
                            ->label('Brand'),
                        
                        TextEntry::make('device_manufacturer')
                            ->label('Manufacturer'),
                        
                        TextEntry::make('device_product')
                            ->label('Product'),
                        
                        TextEntry::make('device_hardware')
                            ->label('Hardware'),
                        
                        TextEntry::make('machine_identifier')
                            ->label('Machine Identifier'),
                        
                        TextEntry::make('system_name')
                            ->label('System Name'),
                        
                        TextEntry::make('system_version')
                            ->label('System Version'),
                        
                        TextEntry::make('vendor_identifier')
                            ->label('Vendor Identifier'),
                        
                        TextEntry::make('android_id')
                            ->label('Android ID'),
                        
                        TextEntry::make('serial_number')
                            ->label('Serial Number'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                
                Section::make('Status & Activity')
                    ->schema([
                        TextEntry::make('last_seen_at')
                            ->label('Last Seen')
                            ->dateTime()
                            ->since()
                            ->color(fn ($state) => $state && $state->isBefore(now()->subHours(24)) ? 'danger' : null)
                            ->icon(fn ($state) => $state && $state->isBefore(now()->subHours(24)) ? 'heroicon-o-exclamation-triangle' : null),
                        
                        TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime(),
                        
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(3),
                
                Section::make('Device Metadata')
                    ->schema([
                        KeyValueEntry::make('device_metadata')
                            ->label('Metadata'),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => !empty($record->device_metadata)),
                
                Section::make('Store')
                    ->schema([
                        TextEntry::make('store.name')
                            ->label('Store Name'),
                        
                        TextEntry::make('store.slug')
                            ->label('Store Slug'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}

