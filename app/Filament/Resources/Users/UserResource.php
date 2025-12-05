<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $recordTitleAttribute = 'name';

    // Disable tenant scoping - User management is for super admins only
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function boot(): void
    {
        parent::boot();
        
        // Disable tenant scoping for user management
        static::scopeToTenant(false);
    }

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.administration');
    }

    public static function canViewAny(): bool
    {
        // Only allow super admins to view users
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        
        return \Filament\Facades\Filament::getTenant() 
            ? $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists()
            : $user->hasRole('super_admin');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Bypass tenant scoping
        $query = User::query()->withoutGlobalScopes();
        
        // Super admins can see all users
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
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Users\RelationManagers\StoresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
