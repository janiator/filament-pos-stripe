<?php

namespace App\Filament\Resources\ConnectedCustomers;

use App\Filament\Resources\ConnectedCustomers\Pages\CreateConnectedCustomer;
use App\Filament\Resources\ConnectedCustomers\Pages\EditConnectedCustomer;
use App\Filament\Resources\ConnectedCustomers\Pages\ListConnectedCustomers;
use App\Filament\Resources\ConnectedCustomers\Pages\ViewConnectedCustomer;
use App\Filament\Resources\ConnectedCustomers\Schemas\ConnectedCustomerForm;
use App\Filament\Resources\ConnectedCustomers\Schemas\ConnectedCustomerInfolist;
use App\Filament\Resources\ConnectedCustomers\Tables\ConnectedCustomersTable;
use App\Models\ConnectedCustomer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedCustomerResource extends Resource
{
    protected static ?string $model = ConnectedCustomer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ConnectedCustomerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedCustomerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedCustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ConnectedCustomers\RelationManagers\SubscriptionsRelationManager::class,
            \App\Filament\Resources\ConnectedCustomers\RelationManagers\PaymentMethodsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnectedCustomers::route('/'),
            'create' => CreateConnectedCustomer::route('/create'),
            'view' => ViewConnectedCustomer::route('/{record}'),
            'edit' => EditConnectedCustomer::route('/{record}/edit'),
        ];
    }
}
