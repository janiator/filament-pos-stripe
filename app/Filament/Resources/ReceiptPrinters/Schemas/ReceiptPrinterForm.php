<?php

namespace App\Filament\Resources\ReceiptPrinters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceiptPrinterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Printer Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Display name for this printer'),
                                
                                Select::make('pos_device_id')
                                    ->label('POS Device')
                                    ->helperText('Optional: Link to a specific POS device')
                                    ->relationship('posDevice', 'device_name', function ($query) {
                                        $tenant = \Filament\Facades\Filament::getTenant();
                                        if ($tenant) {
                                            $query->where('store_id', $tenant->id);
                                        }
                                        return $query;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ]),
                    ]),

                Section::make('Printer Configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('printer_type')
                                    ->label('Printer Type')
                                    ->options([
                                        'epson' => 'Epson ePOS',
                                        'star' => 'Star Micronics',
                                        'other' => 'Other',
                                    ])
                                    ->default('epson')
                                    ->required()
                                    ->reactive(),
                                
                                Select::make('printer_model')
                                    ->label('Printer Model')
                                    ->options([
                                        'tm_m30_80' => 'TM-m30 series (80mm)',
                                        'tm_m30_58' => 'TM-m30 series (58mm)',
                                        'tm_t20_80' => 'TM-T20 (80mm)',
                                        'tm_t20_58' => 'TM-T20 (58mm)',
                                        'tm_t82_80' => 'TM-T82 (80mm)',
                                        'tm_t82_58' => 'TM-T82 (58mm)',
                                        'tm_t88_80' => 'TM-T88 (80mm)',
                                        'tm_t88_58' => 'TM-T88 (58mm)',
                                        'tm_t90_80' => 'TM-T90 (80mm)',
                                        'tm_t90_58' => 'TM-T90 (58mm)',
                                    ])
                                    ->visible(fn ($get) => $get('printer_type') === 'epson')
                                    ->nullable(),
                                
                                Select::make('paper_width')
                                    ->label('Paper Width')
                                    ->options([
                                        '80' => '80mm',
                                        '58' => '58mm',
                                    ])
                                    ->default('80')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Connection Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('connection_type')
                                    ->label('Connection Type')
                                    ->options([
                                        'network' => 'Network (IP)',
                                        'usb' => 'USB',
                                        'bluetooth' => 'Bluetooth',
                                    ])
                                    ->default('network')
                                    ->required()
                                    ->reactive(),
                                
                                TextInput::make('ip_address')
                                    ->label('IP Address')
                                    ->visible(fn ($get) => $get('connection_type') === 'network')
                                    ->required(fn ($get) => $get('connection_type') === 'network')
                                    ->ip()
                                    ->helperText('IP address of the printer'),
                            ]),
                        
                        Grid::make(3)
                            ->schema([
                                TextInput::make('port')
                                    ->label('Port')
                                    ->numeric()
                                    ->default(9100)
                                    ->required()
                                    ->visible(fn ($get) => $get('connection_type') === 'network')
                                    ->helperText('Default: 9100 for ePOS-Print'),
                                
                                TextInput::make('device_id')
                                    ->label('Device ID')
                                    ->default('local_printer')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('ePOS device identifier'),
                                
                                TextInput::make('timeout')
                                    ->label('Timeout (ms)')
                                    ->numeric()
                                    ->default(60000)
                                    ->required()
                                    ->helperText('Print timeout in milliseconds'),
                            ]),
                    ]),

                Section::make('Advanced Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true)
                                        ->helperText('Enable/disable this printer'),
                                    
                                    Toggle::make('use_https')
                                        ->label('Use HTTPS')
                                        ->default(false)
                                        ->helperText('Use HTTPS for secure connection'),
                                    
                                    Toggle::make('monitor_status')
                                        ->label('Monitor Status')
                                        ->default(false)
                                        ->helperText('Monitor printer status (cover, paper, etc.)'),
                                    
                                    Toggle::make('use_job_id')
                                        ->label('Use Print Job ID')
                                        ->default(false)
                                        ->helperText('Use print job ID for tracking'),
                                ]),
                                
                                Group::make([
                                    Select::make('drawer_open_level')
                                        ->label('Drawer Open Level')
                                        ->options([
                                            'low' => 'Low',
                                            'high' => 'High',
                                        ])
                                        ->default('low')
                                        ->required()
                                        ->visible(fn ($get) => $get('monitor_status') === true),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
