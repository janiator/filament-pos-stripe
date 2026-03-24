<?php

namespace App\Filament\Resources\StoreStripePayouts;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\StoreStripePayouts\Pages\ListStoreStripePayouts;
use App\Filament\Resources\StoreStripePayouts\Pages\ViewStoreStripePayout;
use App\Filament\Resources\StoreStripePayouts\Schemas\StoreStripePayoutInfolist;
use App\Filament\Resources\StoreStripePayouts\Tables\StoreStripePayoutsTable;
use App\Models\StoreStripePayout;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StoreStripePayoutResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = StoreStripePayout::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'stripe_payout_id';

    public static function getLabel(): string
    {
        return __('filament.resources.store_stripe_payout.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.store_stripe_payout.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.store_stripe_payout.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.payments');
    }

    public static function getNavigationSort(): ?int
    {
        return 45;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StoreStripePayoutInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreStripePayoutsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreStripePayouts::route('/'),
            'view' => ViewStoreStripePayout::route('/{record}'),
        ];
    }
}
