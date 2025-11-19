<?php

namespace App\Filament\Resources\ConnectedProducts;

use App\Filament\Resources\ConnectedProducts\Pages\CreateConnectedProduct;
use App\Filament\Resources\ConnectedProducts\Pages\EditConnectedProduct;
use App\Filament\Resources\ConnectedProducts\Pages\ListConnectedProducts;
use App\Filament\Resources\ConnectedProducts\Pages\ViewConnectedProduct;
use App\Filament\Resources\ConnectedProducts\Schemas\ConnectedProductForm;
use App\Filament\Resources\ConnectedProducts\Schemas\ConnectedProductInfolist;
use App\Filament\Resources\ConnectedProducts\Tables\ConnectedProductsTable;
use App\Models\ConnectedProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedProductResource extends Resource
{
    protected static ?string $model = ConnectedProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ConnectedProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ConnectedProducts\RelationManagers\PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnectedProducts::route('/'),
            'create' => CreateConnectedProduct::route('/create'),
            'view' => ViewConnectedProduct::route('/{record}'),
            'edit' => EditConnectedProduct::route('/{record}/edit'),
        ];
    }
}
