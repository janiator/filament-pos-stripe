<?php

namespace App\Filament\Resources\Stores;

use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Filament\Resources\Stores\Pages\EditStore;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Resources\Stores\Pages\ViewStore;
use App\Filament\Resources\Stores\Schemas\StoreForm;
use App\Filament\Resources\Stores\Schemas\StoreInfolist;
use App\Filament\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return null; // Stores is the main resource, no group
    }

    public static function canViewAny(): bool
    {
        // Only allow super admins to view all stores
        // Use withoutGlobalScopes to bypass tenant scoping for role checks
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        
        // Temporarily disable tenant scoping for role check
        return \Filament\Facades\Filament::getTenant() 
            ? $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists()
            : $user->hasRole('super_admin');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Super admins can see all stores, others see none
        $user = auth()->user();
        if (!$user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
        
        $isSuperAdmin = \Filament\Facades\Filament::getTenant() 
            ? $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists()
            : $user->hasRole('super_admin');
            
        if ($isSuperAdmin) {
            return parent::getEloquentQuery();
        }
        
        return parent::getEloquentQuery()->whereRaw('1 = 0'); // Return empty query
    }

    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StoreInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Stores\RelationManagers\TerminalLocationsRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\TerminalReadersRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\ConnectedCustomersRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\ConnectedSubscriptionsRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\ConnectedProductsRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\ConnectedChargesRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\ConnectedTransfersRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\ConnectedPaymentMethodsRelationManager::class,
            \App\Filament\Resources\Stores\RelationManagers\ConnectedPaymentLinksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'view' => ViewStore::route('/{record}'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }
}
