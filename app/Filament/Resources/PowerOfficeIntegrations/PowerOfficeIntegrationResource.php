<?php

namespace App\Filament\Resources\PowerOfficeIntegrations;

use App\Enums\AddonType;
use App\Filament\Resources\PowerOfficeIntegrations\Pages\ManagePowerOfficeIntegration;
use App\Filament\Resources\PowerOfficeIntegrations\Schemas\PowerOfficeIntegrationForm;
use App\Filament\Resources\PowerOfficeIntegrations\Tables\PowerOfficeIntegrationsTable;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PowerOfficeIntegrationResource extends Resource
{
    protected static ?string $model = PowerOfficeIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function getNavigationGroup(): ?string
    {
        return 'PowerOffice';
    }

    public static function getModelLabel(): string
    {
        return 'PowerOffice integration';
    }

    public static function getPluralModelLabel(): string
    {
        return 'PowerOffice';
    }

    public static function getNavigationLabel(): string
    {
        return 'PowerOffice';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::PowerOfficeGo);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        try {
            $tenant = Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                $query->where('store_id', $tenant->getKey());
            }
        } catch (\Throwable) {
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return PowerOfficeIntegrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PowerOfficeIntegrationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePowerOfficeIntegration::route('/'),
        ];
    }
}
