<?php

namespace App\Filament\Resources\PowerOfficeIntegrations\Schemas;

use App\Enums\PowerOfficeEnvironment;
use App\Enums\PowerOfficeMappingBasis;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PowerOfficeIntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connection')
                    ->schema([
                        Select::make('environment')
                            ->label('PowerOffice environment')
                            ->options(collect(PowerOfficeEnvironment::cases())->mapWithKeys(
                                fn (PowerOfficeEnvironment $e) => [$e->value => $e->label()]
                            ))
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Accounting')
                    ->schema([
                        Select::make('mapping_basis')
                            ->label('Account mapping basis')
                            ->options(collect(PowerOfficeMappingBasis::cases())->mapWithKeys(
                                fn (PowerOfficeMappingBasis $b) => [$b->value => $b->label()]
                            ))
                            ->required()
                            ->native(false)
                            ->helperText('Choose one dimension to map ledger accounts (VAT, article group, vendor, or payment method).'),
                        Toggle::make('auto_sync_on_z_report')
                            ->label('Automatically sync when a Z-report is generated')
                            ->default(true),
                    ]),
            ]);
    }
}
