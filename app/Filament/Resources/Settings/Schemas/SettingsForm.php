<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Receipt Settings')
                    ->schema([
                        Toggle::make('auto_print_receipts')
                            ->label('Auto Print Receipts')
                            ->helperText('Automatically print receipts after successful purchases')
                            ->default(false)
                            ->columnSpanFull(),

                        Select::make('default_receipt_template_id')
                            ->label('Default Receipt Template')
                            ->relationship('defaultReceiptTemplate', 'template_type', fn ($query) => $query->where('template_type', 'sales'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Default template to use for sales receipts')
                            ->columnSpanFull(),

                        Select::make('receipt_printer_type')
                            ->label('Receipt Printer Type')
                            ->options([
                                'epson' => 'Epson ePOS',
                            ])
                            ->default('epson')
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('receipt_number_format')
                            ->label('Receipt Number Format')
                            ->helperText('Format: {store_id}-{type}-{number:06d}')
                            ->default('{store_id}-{type}-{number:06d}')
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('default_vat_rate')
                            ->label('Default VAT Rate (%)')
                            ->numeric()
                            ->default(25.0)
                            ->suffix('%')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Cash Drawer Settings')
                    ->schema([
                        Toggle::make('cash_drawer_auto_open')
                            ->label('Auto Open Cash Drawer')
                            ->helperText('Automatically open cash drawer for cash payments')
                            ->default(true)
                            ->columnSpan(1),

                        TextInput::make('cash_drawer_open_duration_ms')
                            ->label('Cash Drawer Open Duration (ms)')
                            ->numeric()
                            ->default(250)
                            ->suffix('ms')
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false),

                Section::make('General POS Settings')
                    ->schema([
                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'nok' => 'NOK - Norwegian Krone',
                                'sek' => 'SEK - Swedish Krona',
                                'dkk' => 'DKK - Danish Krone',
                                'eur' => 'EUR - Euro',
                                'usd' => 'USD - US Dollar',
                            ])
                            ->default('nok')
                            ->required()
                            ->columnSpan(1),

                        Select::make('timezone')
                            ->label('Timezone')
                            ->options([
                                'Europe/Oslo' => 'Europe/Oslo (Norway)',
                                'Europe/Stockholm' => 'Europe/Stockholm (Sweden)',
                                'Europe/Copenhagen' => 'Europe/Copenhagen (Denmark)',
                                'Europe/Berlin' => 'Europe/Berlin (Germany)',
                                'UTC' => 'UTC',
                            ])
                            ->default('Europe/Oslo')
                            ->required()
                            ->searchable()
                            ->columnSpan(1),

                        Select::make('locale')
                            ->label('Locale')
                            ->options([
                                'nb' => 'Norwegian Bokmål',
                                'nn' => 'Norwegian Nynorsk',
                                'sv' => 'Swedish',
                                'da' => 'Danish',
                                'en' => 'English',
                            ])
                            ->default('nb')
                            ->required()
                            ->columnSpan(1),

                        Toggle::make('tax_included')
                            ->label('Tax Included in Prices')
                            ->helperText('Whether prices include tax by default')
                            ->default(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
