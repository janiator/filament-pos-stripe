<?php

namespace App\Filament\Resources\Receipts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Information')
                    ->schema([
                        Select::make('store_id')
                            ->relationship('store', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('pos_session_id')
                            ->relationship('posSession', 'session_number')
                            ->searchable()
                            ->preload(),

                        Select::make('charge_id')
                            ->relationship('charge', 'stripe_charge_id')
                            ->searchable()
                            ->preload(),

                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('Cashier')
                            ->searchable()
                            ->preload(),

                        TextInput::make('receipt_number')
                            ->label('Receipt Number')
                            ->required()
                            ->disabled()
                            ->helperText('Automatically generated'),

                        Select::make('receipt_type')
                            ->label('Receipt Type')
                            ->options([
                                'sales' => 'Sales Receipt',
                                'return' => 'Return Receipt',
                                'copy' => 'Copy Receipt',
                                'steb' => 'STEB Receipt',
                                'provisional' => 'Provisional Receipt',
                                'training' => 'Training Receipt',
                                'delivery' => 'Delivery Receipt',
                            ])
                            ->required(),

                        Select::make('original_receipt_id')
                            ->relationship('originalReceipt', 'receipt_number')
                            ->label('Original Receipt')
                            ->searchable()
                            ->preload()
                            ->helperText('For returns/copies'),
                    ])
                    ->columns(2),

                Section::make('Receipt Data')
                    ->schema([
                        KeyValue::make('receipt_data')
                            ->label('Receipt Data (JSON)')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Print Status')
                    ->schema([
                        Toggle::make('printed')
                            ->label('Printed')
                            ->default(false),

                        DateTimePicker::make('printed_at')
                            ->label('Printed At')
                            ->disabled(fn ($record) => !$record || !$record->printed),

                        TextInput::make('reprint_count')
                            ->label('Reprint Count')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
