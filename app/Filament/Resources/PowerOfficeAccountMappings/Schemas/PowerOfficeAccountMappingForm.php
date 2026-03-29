<?php

namespace App\Filament\Resources\PowerOfficeAccountMappings\Schemas;

use App\Enums\PowerOfficeMappingBasis;
use App\Models\Collection as ProductCollection;
use App\Models\PowerOfficeIntegration;
use App\Models\Vendor;
use App\Support\PowerOffice\PowerOfficeStandardVatRates;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PowerOfficeAccountMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basis')
                    ->schema([
                        Select::make('basis_type')
                            ->label('Basis type')
                            ->options(collect(PowerOfficeMappingBasis::cases())->mapWithKeys(
                                fn (PowerOfficeMappingBasis $b) => [$b->value => $b->label()]
                            ))
                            ->required()
                            ->native(false)
                            ->disabled()
                            ->dehydrated()
                            ->default(function (): ?string {
                                $integration = self::currentIntegration();

                                return $integration?->mapping_basis->value;
                            }),
                        Select::make('basis_key')
                            ->label('Basis key')
                            ->required()
                            ->searchable()
                            ->options(fn (): array => self::basisKeyOptions())
                            ->helperText('Must match values from your Z-report (e.g. payment method code, vendor id, VAT rate, or product collection id — use 0 for uncategorized).'),
                        TextInput::make('basis_label')
                            ->maxLength(255),
                    ]),
                Section::make('Ledger accounts (PowerOffice account numbers)')
                    ->schema([
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
                            ->label('Cash clearing account')
                            ->maxLength(64),
                        TextInput::make('card_clearing_account_no')
                            ->label('Card / clearing account')
                            ->maxLength(64),
                        TextInput::make('fees_account_no')
                            ->label('Fees account (optional)')
                            ->helperText('Not used by Z-report PowerOffice sync. Use store PowerOffice → Ledger routing → Payment fees for PSP fees.')
                            ->maxLength(64),
                        TextInput::make('rounding_account_no')
                            ->label('Rounding account')
                            ->maxLength(64),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
            ]);
    }

    protected static function currentIntegration(): ?PowerOfficeIntegration
    {
        $store = Filament::getTenant();
        if (! $store) {
            return null;
        }

        return PowerOfficeIntegration::query()->where('store_id', $store->getKey())->first();
    }

    /**
     * @return array<string, string>
     */
    protected static function basisKeyOptions(): array
    {
        $integration = self::currentIntegration();
        if (! $integration) {
            return [];
        }

        return match ($integration->mapping_basis) {
            PowerOfficeMappingBasis::Vat => PowerOfficeStandardVatRates::options(),
            PowerOfficeMappingBasis::Category => ProductCollection::query()
                ->where('store_id', $integration->store_id)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (ProductCollection $c): array => [(string) $c->getKey() => $c->name])
                ->all() + ['0' => 'Uncategorized'],
            PowerOfficeMappingBasis::Vendor => Vendor::query()
                ->where('store_id', $integration->store_id)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Vendor $v): array => [(string) $v->getKey() => $v->name])
                ->all() + ['no-vendor' => 'Ingen leverandør'],
            PowerOfficeMappingBasis::PaymentMethod => [
                'cash' => 'Cash',
                'card' => 'Card',
                'card_present' => 'Card (terminal)',
                'vipps' => 'Vipps',
                'mobile' => 'Mobile',
            ],
        };
    }
}
