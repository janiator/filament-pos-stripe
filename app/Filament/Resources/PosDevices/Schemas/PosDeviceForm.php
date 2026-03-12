<?php

namespace App\Filament\Resources\PosDevices\Schemas;

use App\Models\TerminalLocation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->helperText('From the device (Android may repeat across devices). Identity per store is device name.'),

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

                        Toggle::make('cash_drawer_enabled')
                            ->label('Cash drawer enabled')
                            ->default(true)
                            ->helperText('When off, only non-cash transactions are allowed on this device'),

                        Toggle::make('has_integrated_drawer')
                            ->label('Has integrated drawer')
                            ->default(false)
                            ->helperText('When on, this device has a built-in cash drawer (e.g. Stripe Terminal S700)'),

                        Toggle::make('booking_enabled')
                            ->label('Booking enabled')
                            ->default(false)
                            ->helperText('When on, this device can sell Merano event tickets if the store has the Merano Booking add-on enabled.'),

                        Toggle::make('auto_print_receipt')
                            ->label('Auto-print receipt')
                            ->default(true)
                            ->helperText('When on, receipts are auto-printed after purchase; when off, printing is optional in the POS app.'),

                        Select::make('terminal_location_id')
                            ->label('Terminal Location')
                            ->helperText('Stripe Terminal location for this device. Each device can have only one location.')
                            ->options(function (Select $component): array {
                                $record = $component->getRecord();
                                $storeId = $record?->store_id ?? \Filament\Facades\Filament::getTenant()?->getKey();
                                if (! $storeId) {
                                    return [];
                                }
                                $query = TerminalLocation::where('store_id', $storeId);
                                if ($record) {
                                    $query->where(function ($q) use ($record): void {
                                        $q->whereNull('pos_device_id')
                                            ->orWhere('pos_device_id', $record->id);
                                    });
                                } else {
                                    $query->whereNull('pos_device_id');
                                }

                                return $query->pluck('display_name', 'id')->all();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable(),
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
