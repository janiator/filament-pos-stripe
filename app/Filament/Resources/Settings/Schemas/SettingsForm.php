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
                            ->label(__('Auto Print Receipts'))
                            ->helperText(__('Automatically print receipts after successful purchases'))
                            ->default(false)
                            ->columnSpanFull(),

                        Select::make('default_receipt_template_id')
                            ->label(__('Default Receipt Template'))
                            ->relationship('defaultReceiptTemplate', 'template_type', fn ($query) => $query->where('template_type', 'sales'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(__('Default template to use for sales receipts'))
                            ->columnSpanFull(),

                        Select::make('receipt_printer_type')
                            ->label(__('Receipt Printer Type'))
                            ->options([
                                'epson' => 'Epson ePOS',
                            ])
                            ->default('epson')
                            ->required()
                            ->rules(['required', 'string', 'in:epson'])
                            ->columnSpan(1),

                        TextInput::make('receipt_number_format')
                            ->label(__('Receipt Number Format'))
                            ->helperText(__('Format: {store_id}-{type}-{number:06d}'))
                            ->default('{store_id}-{type}-{number:06d}')
                            ->required()
                            ->rules(['required', 'string', 'max:255'])
                            ->columnSpan(1),

                        TextInput::make('default_vat_rate')
                            ->label(__('Default VAT Rate (%)'))
                            ->numeric()
                            ->default(25.0)
                            ->suffix(__('%'))
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
                            ->label(__('Auto Open Cash Drawer'))
                            ->helperText(__('Automatically open cash drawer for cash payments'))
                            ->default(true)
                            ->columnSpan(1),

                        TextInput::make('cash_drawer_open_duration_ms')
                            ->label(__('Cash Drawer Open Duration (ms)'))
                            ->numeric()
                            ->default(250)
                            ->suffix(__('ms'))
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
                            ->label(__('Currency'))
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
                            ->label(__('Timezone'))
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
                            ->label(__('Locale'))
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
                            ->label(__('Tax Included in Prices'))
                            ->helperText(__('Whether prices include tax by default'))
                            ->default(false)
                            ->columnSpan(1),

                        Toggle::make('tips_enabled')
                            ->label(__('Enable Tips'))
                            ->helperText(__('Allow tips to be added to transactions. When disabled, tips will be hidden from reports.'))
                            ->default(true)
                            ->columnSpan(1),

                        Toggle::make('customers_enabled')
                            ->label(__('Enable Customers in POS'))
                            ->helperText(__('When disabled, customer-related features are hidden in the POS app (e.g. linking customers to purchases).'))
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Gift Card Settings')
                    ->schema([
                        TextInput::make('gift_card_expiration_days')
                            ->label(__('Default Gift Card Expiration (Days)'))
                            ->numeric()
                            ->default(365)
                            ->helperText(__('Default number of days until gift cards expire. Leave empty for no expiration.'))
                            ->minValue(1)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Section::make('Register sessions')
                    ->schema([
                        Toggle::make('auto_close_open_sessions_daily')
                            ->label(__('Auto-close open sessions daily'))
                            ->helperText(__('When enabled for this store, still-open register sessions are closed once per day by the server schedule (see POS_AUTO_CLOSE_SESSIONS_TIME in deployment config). Actual cash is set to expected cash. Requires Laravel scheduler (e.g. cron running `schedule:run`).'))
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
