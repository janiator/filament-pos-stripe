<?php

namespace App\Filament\Resources\ConnectedPrices;

use App\Filament\Resources\ConnectedPrices\Pages\CreateConnectedPrice;
use App\Filament\Resources\ConnectedPrices\Pages\EditConnectedPrice;
use App\Filament\Resources\ConnectedPrices\Pages\ListConnectedPrices;
use App\Filament\Resources\ConnectedPrices\Pages\ViewConnectedPrice;
use App\Filament\Resources\ConnectedPrices\Schemas\ConnectedPriceForm;
use App\Filament\Resources\ConnectedPrices\Schemas\ConnectedPriceInfolist;
use App\Filament\Resources\ConnectedPrices\Tables\ConnectedPricesTable;
use App\Models\ConnectedPrice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedPriceResource extends Resource
{
    protected static ?string $model = ConnectedPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    // Prices are managed through products, so hide from navigation
    protected static bool $shouldRegisterNavigation = false;

    public static function getLabel(): string
    {
        return 'Price';
    }

    public static function getPluralLabel(): string
    {
        return 'Prices';
    }

    public static function getNavigationLabel(): string
    {
        return 'Prices';
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectedPriceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedPriceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedPricesTable::configure($table);
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
            'index' => ListConnectedPrices::route('/'),
            'create' => CreateConnectedPrice::route('/create'),
            'view' => ViewConnectedPrice::route('/{record}'),
            'edit' => EditConnectedPrice::route('/{record}/edit'),
        ];
    }
}
