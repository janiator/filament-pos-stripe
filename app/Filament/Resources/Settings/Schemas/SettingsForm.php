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
                            ->rules(['required', 'string', 'in:epson'])
                            ->columnSpan(1),

                        TextInput::make('receipt_number_format')
                            ->label('Receipt Number Format')
                            ->helperText('Format: {store_id}-{type}-{number:06d}')
                            ->default('{store_id}-{type}-{number:06d}')
                            ->required()
                            ->rules(['required', 'string', 'max:255'])
                            ->columnSpan(1),

                        TextInput::make('default_vat_rate')
                            ->label('Default VAT Rate (%)')
                            ->numeric()
                            ->default(25.0)
                            ->suffix('%')
                            ->required()
                            ->rules(['required', 'numeric', 'min:0', 'max:100'])
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
                            ->rules(['required', 'integer', 'min:0'])
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
                            ->rules(['required', 'string', 'in:nok,sek,dkk,eur,usd'])
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
                            ->rules(['required', 'string'])
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
                            ->rules(['required', 'string', 'in:nb,nn,sv,da,en'])
                            ->columnSpan(1),

                        Toggle::make('tax_included')
                            ->label('Tax Included in Prices')
                            ->helperText('Whether prices include tax by default')
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('tips_enabled')
                            ->label('Enable Tips')
                            ->helperText('Allow tips to be added to transactions. When disabled, tips will be hidden from reports.')
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Gift Card Settings')
                    ->schema([
                        TextInput::make('gift_card_expiration_days')
                            ->label('Default Gift Card Expiration (Days)')
                            ->numeric()
                            ->default(365)
                            ->helperText('Default number of days until gift cards expire. Leave empty for no expiration.')
                            ->minValue(1)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
