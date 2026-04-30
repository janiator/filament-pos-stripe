<?php

namespace App\Filament\Resources\PowerOfficeAccountMappings;

use App\Filament\Resources\PowerOfficeAccountMappings\Pages\CreatePowerOfficeAccountMapping;
use App\Filament\Resources\PowerOfficeAccountMappings\Pages\EditPowerOfficeAccountMapping;
use App\Filament\Resources\PowerOfficeAccountMappings\Pages\ListPowerOfficeAccountMappings;
use App\Filament\Resources\PowerOfficeAccountMappings\Schemas\PowerOfficeAccountMappingForm;
use App\Filament\Resources\PowerOfficeAccountMappings\Tables\PowerOfficeAccountMappingsTable;
use App\Models\PowerOfficeAccountMapping;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PowerOfficeAccountMappingResource extends Resource
{
    protected static ?string $model = PowerOfficeAccountMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public static function getNavigationGroup(): ?string
    {
        return 'PowerOffice';
    }

    public static function getModelLabel(): string
    {
        return 'Account mapping';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Account mappings';
    }

    public static function getNavigationLabel(): string
    {
        return 'Account mappings';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
        return PowerOfficeAccountMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PowerOfficeAccountMappingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPowerOfficeAccountMappings::route('/'),
            'create' => CreatePowerOfficeAccountMapping::route('/create'),
            'edit' => EditPowerOfficeAccountMapping::route('/{record}/edit'),
        ];
    }
}
