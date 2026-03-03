<?php

namespace App\Filament\Resources\QuantityUnits;

use App\Filament\Resources\QuantityUnits\Pages\CreateQuantityUnit;
use App\Filament\Resources\QuantityUnits\Pages\EditQuantityUnit;
use App\Filament\Resources\QuantityUnits\Pages\ListQuantityUnits;
use App\Filament\Resources\QuantityUnits\Pages\ViewQuantityUnit;
use App\Filament\Resources\QuantityUnits\Schemas\QuantityUnitForm;
use App\Filament\Resources\QuantityUnits\Schemas\QuantityUnitInfolist;
use App\Filament\Resources\QuantityUnits\Tables\QuantityUnitsTable;
use App\Models\QuantityUnit;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuantityUnitResource extends Resource
{
    protected static ?string $model = QuantityUnit::class;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        try {
            $tenant = Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                $query->where(function ($q) use ($tenant) {
                    $q->where(function ($q2) {
                        $q2->whereNull('store_id')->whereNull('stripe_account_id');
                    })->orWhereHas('store', fn ($q2) => $q2->where('stores.id', $tenant->id));
                });
            } else {
                $query->whereNull('store_id')->whereNull('stripe_account_id');
            }
        } catch (\Throwable) {
            //
        }

        return $query;
    }

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public static function form(Schema $schema): Schema
    {
        return QuantityUnitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuantityUnitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuantityUnitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getLabel(): string
    {
        return __('filament.resources.quantity_unit.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.quantity_unit.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.quantity_unit.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.catalog');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuantityUnits::route('/'),
            'create' => CreateQuantityUnit::route('/create'),
            'view' => ViewQuantityUnit::route('/{record}'),
            'edit' => EditQuantityUnit::route('/{record}/edit'),
        ];
    }
}
