<?php

namespace App\Filament\Resources\PosDevices\RelationManagers;

use App\Models\ReceiptPrinter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReceiptPrintersRelationManager extends RelationManager
{
    protected static string $relationship = 'receiptPrinters';

    protected static ?string $title = 'Receipt Printers';

    public function form(Schema $schema): Schema
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
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
                    ->searchable()
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
                Tables\Filters\SelectFilter::make('printer_type')
                    ->label('Printer Type')
                    ->options([
                        'epson' => 'Epson ePOS',
                        'star' => 'Star Micronics',
                        'other' => 'Other',
                    ]),
                
                Tables\Filters\SelectFilter::make('connection_type')
                    ->label('Connection Type')
                    ->options([
                        'network' => 'Network',
                        'usb' => 'USB',
                        'bluetooth' => 'Bluetooth',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All printers')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Set store_id and pos_device_id from the owner record
                        /** @var \App\Models\PosDevice $posDevice */
                        $posDevice = $this->getOwnerRecord();
                        $data['store_id'] = $posDevice->store_id;
                        $data['pos_device_id'] = $posDevice->id;
                        return $data;
                    }),
                
                Action::make('attach')
                    ->label('Attach Existing Printer')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('receipt_printer_id')
                            ->label('Receipt Printer')
                            ->options(function () {
                                /** @var \App\Models\PosDevice $posDevice */
                                $posDevice = $this->getOwnerRecord();
                                
                                return ReceiptPrinter::where('store_id', $posDevice->store_id)
                                    ->where(function ($query) use ($posDevice) {
                                        $query->whereNull('pos_device_id')
                                              ->orWhere('pos_device_id', '!=', $posDevice->id);
                                    })
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required()
                            ->preload(),
                    ])
                    ->action(function (array $data): void {
                        /** @var \App\Models\PosDevice $posDevice */
                        $posDevice = $this->getOwnerRecord();
                        
                        ReceiptPrinter::where('id', $data['receipt_printer_id'])
                            ->where('store_id', $posDevice->store_id)
                            ->update(['pos_device_id' => $posDevice->id]);
                    })
                    ->successNotificationTitle('Receipt printer attached'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                Action::make('detach')
                    ->label('Detach')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ReceiptPrinter $record): void {
                        // Detach by setting pos_device_id to null
                        $record->update(['pos_device_id' => null]);
                    })
                    ->successNotificationTitle('Receipt printer detached'),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('detach')
                        ->label('Detach Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            // Detach by setting pos_device_id to null
                            ReceiptPrinter::whereIn('id', $records->pluck('id'))
                                ->update(['pos_device_id' => null]);
                        })
                        ->successNotificationTitle('Receipt printers detached'),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}

