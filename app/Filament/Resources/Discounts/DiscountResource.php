<?php

namespace App\Filament\Resources\Discounts;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\Discounts\Pages\CreateDiscount;
use App\Filament\Resources\Discounts\Pages\EditDiscount;
use App\Filament\Resources\Discounts\Pages\ListDiscounts;
use App\Filament\Resources\Discounts\Schemas\DiscountForm;
use App\Filament\Resources\Discounts\Tables\DiscountsTable;
use App\Models\Discount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiscountResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = Discount::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getLabel(): string
    {
        return __('filament.resources.discount.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.discount.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.discount.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.catalog');
    }

    public static function form(Schema $schema): Schema
    {
        return DiscountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscounts::route('/'),
            'create' => CreateDiscount::route('/create'),
            'edit' => EditDiscount::route('/{record}/edit'),
        ];
    }
}
