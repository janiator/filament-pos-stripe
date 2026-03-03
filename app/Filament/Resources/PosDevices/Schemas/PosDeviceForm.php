<?php

namespace App\Filament\Resources\PosDevices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\KeyValue;

class PosDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Device Information')
                    ->schema([
                        TextInput::make('device_name')
                            ->label('Device Name')
                            ->required()
                            ->maxLength(255),
                        
                        TextInput::make('device_identifier')
                            ->label('Device Identifier')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: function ($rule) {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant) {
                                        $rule->where('store_id', $tenant->id);
                                    }
                                    return $rule;
                                }
                            )
                            ->disabled(fn ($record) => $record !== null)
                            ->helperText('Unique per store. Same device can be registered on multiple stores (iOS: identifierForVendor, Android: androidId)'),
                        
                        Select::make('platform')
                            ->label('Platform')
                            ->options([
                                'ios' => 'iOS',
                                'android' => 'Android',
                            ])
                            ->required()
                            ->native(false),
                        
                        Select::make('device_status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'maintenance' => 'Maintenance',
                                'offline' => 'Offline',
                            ])
                            ->required()
                            ->default('active')
                            ->native(false),
                        
                        Select::make('default_printer_id')
                            ->label('Default Receipt Printer')
                            ->relationship(
                                'defaultPrinter',
                                'name',
                                modifyQueryUsing: function ($query) {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if ($tenant) {
                                        $query->where('store_id', $tenant->id);
                                    }
                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Select the default receipt printer for this POS device'),
                    ])
                    ->columns(2),
                
                Section::make('Device Details')
                    ->schema([
                        TextInput::make('device_model')
                            ->label('Model')
                            ->maxLength(255),
                        
                        TextInput::make('device_brand')
                            ->label('Brand')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),
                        
                        TextInput::make('device_manufacturer')
                            ->label('Manufacturer')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),
                        
                        TextInput::make('device_product')
                            ->label('Product')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),
                        
                        TextInput::make('device_hardware')
                            ->label('Hardware')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),
                        
                        TextInput::make('machine_identifier')
                            ->label('Machine Identifier')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'ios')
                            ->helperText('iOS: utsname.machine (e.g., "iPad13,1")'),
                        
                        TextInput::make('system_name')
                            ->label('System Name')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'ios')
                            ->helperText('iOS: systemName'),
                        
                        TextInput::make('system_version')
                            ->label('System Version')
                            ->maxLength(255),
                        
                        TextInput::make('vendor_identifier')
                            ->label('Vendor Identifier')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'ios')
                            ->helperText('iOS: identifierForVendor'),
                        
                        TextInput::make('android_id')
                            ->label('Android ID')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),
                        
                        TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),
                
                Section::make('Status & Metadata')
                    ->schema([
                        DateTimePicker::make('last_seen_at')
                            ->label('Last Seen At')
                            ->displayFormat('Y-m-d H:i:s')
                            ->timezone('UTC'),
                        
                        KeyValue::make('device_metadata')
                            ->label('Device Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Additional device information (battery, storage, etc.)'),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }
}

