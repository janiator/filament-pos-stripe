<?php

namespace App\Filament\Resources\PosDevices\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PosDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Device Information')
                    ->schema([
                        TextInput::make('device_name')
                            ->label(__('Device Name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('device_identifier')
                            ->label(__('Device Identifier'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('From the device (Android may repeat across devices). Identity per store is device name.')),

                        Select::make('platform')
                            ->label(__('Platform'))
                            ->options([
                                'ios' => 'iOS',
                                'android' => 'Android',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('device_status')
                            ->label(__('Status'))
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
                            ->label(__('Default Receipt Printer'))
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
                            ->helperText(__('Select the default receipt printer for this POS device')),

                        Toggle::make('cash_drawer_enabled')
                            ->label(__('Cash drawer enabled'))
                            ->default(true)
                            ->helperText(__('When off, only non-cash transactions are allowed on this device')),

                        Toggle::make('has_integrated_drawer')
                            ->label(__('Has integrated drawer'))
                            ->default(false)
                            ->helperText(__('When on, this device has a built-in cash drawer (e.g. Stripe Terminal S700)')),

                        Toggle::make('booking_enabled')
                            ->label(__('Booking enabled'))
                            ->default(false)
                            ->helperText(__('When on, this device can sell Merano event tickets if the store has the Merano Booking add-on enabled.')),

                        Toggle::make('auto_print_receipt')
                            ->label(__('Auto-print receipt'))
                            ->default(true)
                            ->helperText(__('When on, receipts are auto-printed after purchase; when off, printing is optional in the POS app.')),

                        Select::make('terminal_location_id')
                            ->label(__('Terminal Location'))
                            ->helperText(__('Stripe Terminal location for this device.'))
                            ->relationship(
                                'terminalLocation',
                                'display_name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $tenant = \Filament\Facades\Filament::getTenant();
                                    if (! $tenant) {
                                        return $query;
                                    }

                                    return $query->where('store_id', $tenant->id);
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Device Details')
                    ->schema([
                        TextInput::make('device_model')
                            ->label(__('Model'))
                            ->maxLength(255),

                        TextInput::make('device_brand')
                            ->label(__('Brand'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),

                        TextInput::make('device_manufacturer')
                            ->label(__('Manufacturer'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),

                        TextInput::make('device_product')
                            ->label(__('Product'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),

                        TextInput::make('device_hardware')
                            ->label(__('Hardware'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),

                        TextInput::make('machine_identifier')
                            ->label(__('Machine Identifier'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'ios')
                            ->helperText(__('iOS: utsname.machine (e.g., "iPad13,1")')),

                        TextInput::make('system_name')
                            ->label(__('System Name'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'ios')
                            ->helperText(__('iOS: systemName')),

                        TextInput::make('system_version')
                            ->label(__('System Version'))
                            ->maxLength(255),

                        TextInput::make('vendor_identifier')
                            ->label(__('Vendor Identifier'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'ios')
                            ->helperText(__('iOS: identifierForVendor')),

                        TextInput::make('android_id')
                            ->label(__('Android ID'))
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('platform') === 'android'),

                        TextInput::make('serial_number')
                            ->label(__('Serial Number'))
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Status & Metadata')
                    ->schema([
                        DateTimePicker::make('last_seen_at')
                            ->label(__('Last Seen At'))
                            ->displayFormat('Y-m-d H:i:s')
                            ->timezone('UTC'),

                        KeyValue::make('device_metadata')
                            ->label(__('Device Metadata'))
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText(__('Additional device information (battery, storage, etc.)')),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }
}
