<?php

namespace App\Filament\Resources\TripletexIntegrations\Schemas;

use App\Enums\PowerOfficeMappingBasis;
use App\Enums\TripletexEnvironment;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TripletexIntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connection')
                    ->description('Consumer and employee tokens from Tripletex (API 2.0).')
                    ->schema([
                        Select::make('environment')
                            ->label('Tripletex environment')
                            ->options(collect(TripletexEnvironment::cases())->mapWithKeys(
                                fn (TripletexEnvironment $e): array => [$e->value => $e->label()]
                            ))
                            ->required()
                            ->native(false),
                        TextInput::make('consumer_token')
                            ->label('Consumer token')
                            ->password()
                            ->revealable()
                            ->maxLength(2048),
                        TextInput::make('employee_token')
                            ->label('Employee token')
                            ->password()
                            ->revealable()
                            ->maxLength(2048),
                    ]),
                Section::make('Sync')
                    ->schema([
                        Toggle::make('sync_enabled')
                            ->label('Tripletex sync enabled')
                            ->default(true),
                        Toggle::make('auto_sync_on_z_report')
                            ->label('Sync automatically when a Z-report is generated')
                            ->default(true)
                            ->visible(fn (Get $get): bool => (bool) $get('sync_enabled')),
                        Toggle::make('auto_sync_payouts')
                            ->label('Sync automatically when a Stripe payout is marked paid')
                            ->default(false)
                            ->visible(fn (Get $get): bool => (bool) $get('sync_enabled')),
                        Toggle::make('z_report_include_settlement')
                            ->label('Include fee/payout settlement lines on Z-report vouchers')
                            ->helperText('When off (recommended), payout sync posts bank/fees separately. When on, Z-report vouchers include the same paired fee/payout lines as PowerOffice.')
                            ->default(false)
                            ->visible(fn (Get $get): bool => (bool) $get('sync_enabled')),
                    ]),
                Section::make('Accounting')
                    ->schema([
                        Select::make('mapping_basis')
                            ->label('How to split ledger lines')
                            ->options(collect(PowerOfficeMappingBasis::cases())->mapWithKeys(
                                fn (PowerOfficeMappingBasis $b): array => [$b->value => $b->label()]
                            ))
                            ->required()
                            ->live()
                            ->native(false),
                        Section::make('Revenue account by VAT rate')
                            ->description('Only rates you expect on Z-reports need a number.')
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Vat->value)
                            ->columns(1)
                            ->schema(collect(PowerOfficeStandardVatRates::options())
                                ->map(fn (string $label, string $key): TextInput => TextInput::make('vat_sales_'.$key)
                                    ->label($label.' — sales / revenue account')
                                    ->maxLength(64))
                                ->values()
                                ->all()),
                        Section::make('VAT, tips, rounding, and payment fallbacks')
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Vat->value)
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_shared_vat_account_no')
                                    ->label('VAT account (output VAT)')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_tips_account_no')
                                    ->label('Tips account')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_cash_account_no')
                                    ->label('Cash account (fallback)')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_card_clearing_account_no')
                                    ->label('Card / clearing account (fallback)')
                                    ->maxLength(64),
                                TextInput::make('ledger_shared_rounding_account_no')
                                    ->label('Rounding account')
                                    ->maxLength(64),
                            ]),
                        Repeater::make('mappings')
                            ->label('Account numbers per line')
                            ->visible(fn (Get $get): bool => $get('mapping_basis') !== PowerOfficeMappingBasis::Vat->value)
                            ->schema([
                                TextInput::make('basis_key')
                                    ->label('Line key')
                                    ->required()
                                    ->maxLength(191),
                                TextInput::make('basis_label')
                                    ->label('Description')
                                    ->maxLength(255),
                                TextInput::make('sales_account_no')
                                    ->label('Sales / revenue account')
                                    ->required()
                                    ->maxLength(64),
                                TextInput::make('vat_account_no')
                                    ->label('VAT account')
                                    ->maxLength(64),
                                TextInput::make('tips_account_no')
                                    ->label('Tips account')
                                    ->maxLength(64),
                                TextInput::make('cash_account_no')
                                    ->label('Cash account')
                                    ->maxLength(64),
                                TextInput::make('card_clearing_account_no')
                                    ->label('Card / clearing account')
                                    ->maxLength(64),
                                TextInput::make('fees_account_no')
                                    ->label('Fees account (optional)')
                                    ->maxLength(64),
                                TextInput::make('rounding_account_no')
                                    ->label('Rounding account')
                                    ->maxLength(64),
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ])
                            ->addActionLabel('Add line')
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
                Section::make('Ledger routing (payments & settlement)')
                    ->description('Account numbers for payment debits, gift cards, fees, and payouts (same layout as PowerOffice).')
                    ->schema([
                        TextInput::make('ledger_default_sales_account_no')
                            ->label('Default sales / revenue account (fallback)')
                            ->visible(fn (Get $get): bool => in_array($get('mapping_basis'), [
                                PowerOfficeMappingBasis::Category->value,
                                PowerOfficeMappingBasis::Vendor->value,
                            ], true))
                            ->maxLength(64),
                        Section::make('Debit accounts per payment method (Z-report net)')
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_payment_debit_cash')
                                    ->label('cash')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_card_present')
                                    ->label('card_present')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_card')
                                    ->label('card')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_vipps')
                                    ->label('vipps')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_mobile')
                                    ->label('mobile')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_gift_token')
                                    ->label('gift_token')
                                    ->maxLength(64),
                                TextInput::make('ledger_payment_debit_default')
                                    ->label('Default')
                                    ->maxLength(64),
                            ]),
                        TextInput::make('ledger_giftcard_liability_account_no')
                            ->label('Gift card liability account')
                            ->maxLength(64),
                        TextInput::make('ledger_interim_liquid_account_no')
                            ->label('Interim / PSP liquid account (reference)')
                            ->maxLength(64),
                        Section::make('Payment fees (paired posting)')
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_fee_credit_account_no')
                                    ->label('Fee settlement account (credit)')
                                    ->maxLength(64),
                                TextInput::make('ledger_fee_debit_account_no')
                                    ->label('Fee expense account (debit)')
                                    ->maxLength(64),
                            ]),
                        Section::make('Payout to bank (paired posting)')
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_payout_credit_account_no')
                                    ->label('Payout settlement account (credit)')
                                    ->maxLength(64),
                                TextInput::make('ledger_payout_debit_bank_account_no')
                                    ->label('Bank account (debit)')
                                    ->maxLength(64),
                            ]),
                        Section::make('Stripe fee split (payout vouchers)')
                            ->description('Optional application-fee expense account: when set and mirror data includes fee breakdown, payout vouchers post separate clearing/expense pairs for application fee vs Stripe processing fee.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('ledger_application_fee_debit_account_no')
                                    ->label('Application fee expense (debit)')
                                    ->maxLength(64),
                                TextInput::make('ledger_app_fee_supplier_id')
                                    ->label('Application fee supplier (Tripletex id)')
                                    ->numeric()
                                    ->minValue(1),
                            ]),
                        Section::make('Z-report: Tripletex VAT types (optional)')
                            ->description('When set, voucher postings include Tripletex vatType ids for sales and output VAT lines.')
                            ->visible(fn (Get $get): bool => $get('mapping_basis') === PowerOfficeMappingBasis::Vat->value)
                            ->columns(2)
                            ->schema([
                                ...collect(PowerOfficeStandardVatRates::basisKeys())
                                    ->map(fn (string $key): TextInput => TextInput::make('ledger_tripletex_vat_sales_'.$key)
                                        ->label('Tripletex VAT type id — sales '.$key.'%')
                                        ->numeric()
                                        ->minValue(1))
                                    ->all(),
                                TextInput::make('ledger_tripletex_vat_output_vat')
                                    ->label('Tripletex VAT type id — output VAT line')
                                    ->numeric()
                                    ->minValue(1)
                                    ->columnSpanFull(),
                            ]),
                        Toggle::make('ledger_z_report_split_lines_by_calendar_day')
                            ->label('Z-report: split ledger lines by calendar day')
                            ->helperText('When enabled, uses succeeded session charges to allocate amounts per calendar day (app timezone) with posting_date on each line. Falls back to a single day if no charge data.')
                            ->default(false),
                        Section::make('External / web ticket sales on payout vouchers')
                            ->description('Optional paired postings for charges that look like web/Merano ticket sales (not linked to a POS session). Avoid double-counting with POS-attributed charges.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('ledger_external_ticket_sales_enabled')
                                    ->label('Enable external ticket lines')
                                    ->default(false)
                                    ->columnSpanFull(),
                                TextInput::make('ledger_external_ticket_sales_account_no')
                                    ->label('Sales / revenue account (credit)')
                                    ->maxLength(64),
                                TextInput::make('ledger_external_ticket_clearing_account_no')
                                    ->label('Clearing account override (debit)')
                                    ->helperText('Leave empty to use the payout settlement credit account.')
                                    ->maxLength(64),
                                TextInput::make('ledger_external_ticket_vat_type_id')
                                    ->label('Tripletex VAT type id (optional)')
                                    ->numeric()
                                    ->minValue(1),
                                TextInput::make('ledger_external_ticket_metadata_keys')
                                    ->label('Required metadata keys (comma-separated)')
                                    ->placeholder('booking_id')
                                    ->maxLength(512)
                                    ->columnSpanFull(),
                                TextInput::make('ledger_external_ticket_description_regex')
                                    ->label('Optional charge description regex (PCRE)')
                                    ->maxLength(512)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
