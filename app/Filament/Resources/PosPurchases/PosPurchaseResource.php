<?php

namespace App\Filament\Resources\PosPurchases;

use App\Filament\Resources\PosPurchases\Pages\ListPosPurchases;
use App\Filament\Resources\PosPurchases\Pages\ViewPosPurchase;
use App\Filament\Resources\PosPurchases\Schemas\PosPurchaseInfolist;
use App\Filament\Resources\PosPurchases\Tables\PosPurchasesTable;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Models\ConnectedCharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PosPurchaseResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ConnectedCharge::class;

    // Disable automatic tenant scoping - we'll handle it manually
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = null;

    public static function getLabel(): string
    {
        return __('filament.resources.pos_purchase.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.pos_purchase.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.pos_purchase.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.pos_system');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        
        // Only show charges that are POS purchases (have pos_session_id)
        $query->whereNotNull('pos_session_id');
        
        // Scope by tenant's stripe_account_id
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin' && $tenant->stripe_account_id) {
                $query->where('connected_charges.stripe_account_id', $tenant->stripe_account_id);
            }
        } catch (\Throwable $e) {
            // Fallback if Filament facade not available
        }
        
        return $query;
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        if (! $record) {
            return null;
        }
        
        $chargeId = $record->stripe_charge_id ?? 'Cash #' . $record->id;
        return $chargeId . ' - ' . $record->formatted_amount;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PosPurchaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosPurchasesTable::configure($table);
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
            'index' => ListPosPurchases::route('/'),
            'view' => ViewPosPurchase::route('/{record}'),
            // Purchases should only be created via API, not manually
            // 'create' => CreatePosPurchase::route('/create'),
            // 'edit' => EditPosPurchase::route('/{record}/edit'),
        ];
    }
}
