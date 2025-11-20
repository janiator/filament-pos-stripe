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

    // Disable tenant scoping - Store IS the tenant model
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function boot(): void
    {
        parent::boot();
        
        // Completely disable tenant scoping for this resource
        // Store IS the tenant, so it cannot be scoped to itself
        static::scopeToTenant(false);
    }

    public static function isScopedToTenant(): bool
    {
        // Explicitly return false - Store IS the tenant model
        return false;
    }

    public static function scopeEloquentQueryToTenant(\Illuminate\Database\Eloquent\Builder $query, ?\Illuminate\Database\Eloquent\Model $tenant = null): \Illuminate\Database\Eloquent\Builder
    {
        // Don't scope to tenant - StoreResource is for super admins to manage all stores
        // If the query model is Store (the tenant), just return the query as-is
        if ($query->getModel()::class === Store::class) {
            return $query;
        }
        return $query;
    }

    public static function getTenantOwnershipRelationshipName(): string
    {
        // Return empty string to prevent Filament from trying to find a relationship
        // Store IS the tenant, so there's no ownership relationship
        return '';
    }

    public static function getTenantOwnershipRelationship(\Illuminate\Database\Eloquent\Model $record): \Illuminate\Database\Eloquent\Relations\Relation
    {
        // This should never be called since isScopedToTenant() returns false
        // But if it is called, we need to handle it gracefully
        // Since the method signature requires a Relation, we can't return null
        // The best we can do is throw a clear exception
        throw new \LogicException('StoreResource has tenant scoping disabled. getTenantOwnershipRelationship() should not be called.');
    }

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
        // Bypass Filament's tenant scoping entirely by querying Store directly
        // Store IS the tenant, so we can't use parent::getEloquentQuery() which tries to apply tenant scoping
        $query = Store::query()->withoutGlobalScopes();
        
        // Super admins can see all stores, others see none
        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }
        
        $isSuperAdmin = \Filament\Facades\Filament::getTenant() 
            ? $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists()
            : $user->hasRole('super_admin');
            
        if (!$isSuperAdmin) {
            return $query->whereRaw('1 = 0'); // Return empty query
        }
        
        return $query;
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
