<?php

namespace App\Filament\Resources\ConnectedSubscriptions;

use App\Filament\Resources\ConnectedSubscriptions\Pages\CreateConnectedSubscription;
use App\Filament\Resources\ConnectedSubscriptions\Pages\EditConnectedSubscription;
use App\Filament\Resources\ConnectedSubscriptions\Pages\ListConnectedSubscriptions;
use App\Filament\Resources\ConnectedSubscriptions\Pages\ViewConnectedSubscription;
use App\Filament\Resources\ConnectedSubscriptions\Schemas\ConnectedSubscriptionForm;
use App\Filament\Resources\ConnectedSubscriptions\Schemas\ConnectedSubscriptionInfolist;
use App\Filament\Resources\ConnectedSubscriptions\Tables\ConnectedSubscriptionsTable;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Models\ConnectedSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedSubscriptionResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ConnectedSubscription::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getLabel(): string
    {
        return __('filament.resources.connected_subscription.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.connected_subscription.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.connected_subscription.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.customers');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectedSubscriptionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedSubscriptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedSubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\ConnectedSubscriptions\RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnectedSubscriptions::route('/'),
            'create' => CreateConnectedSubscription::route('/create'),
            'view' => ViewConnectedSubscription::route('/{record}'),
            'edit' => EditConnectedSubscription::route('/{record}/edit'),
        ];
    }
}
