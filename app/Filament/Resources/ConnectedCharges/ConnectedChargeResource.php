<?php

namespace App\Filament\Resources\ConnectedCharges;

use App\Filament\Resources\ConnectedCharges\Pages\CreateConnectedCharge;
use App\Filament\Resources\ConnectedCharges\Pages\EditConnectedCharge;
use App\Filament\Resources\ConnectedCharges\Pages\ListConnectedCharges;
use App\Filament\Resources\ConnectedCharges\Pages\ViewConnectedCharge;
use App\Filament\Resources\ConnectedCharges\Schemas\ConnectedChargeForm;
use App\Filament\Resources\ConnectedCharges\Schemas\ConnectedChargeInfolist;
use App\Filament\Resources\ConnectedCharges\Tables\ConnectedChargesTable;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Models\ConnectedCharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedChargeResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ConnectedCharge::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin' && $tenant->stripe_account_id) {
                // ConnectedCharge uses stripe_account_id, not store_id
                $query->where('connected_charges.stripe_account_id', $tenant->stripe_account_id);
            }
        } catch (\Throwable $e) {
            // Fallback if Filament facade not available
        }
        
        return $query;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'stripe_charge_id';

    public static function getLabel(): string
    {
        return __('filament.resources.connected_charge.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.connected_charge.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.connected_charge.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.payments');
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        if (! $record) {
            return null;
        }
        return $record->formatted_amount . ' - ' . ($record->description ?? $record->stripe_charge_id);
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectedChargeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedChargeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedChargesTable::configure($table);
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
            'index' => ListConnectedCharges::route('/'),
            'create' => CreateConnectedCharge::route('/create'),
            'view' => ViewConnectedCharge::route('/{record}'),
            'edit' => EditConnectedCharge::route('/{record}/edit'),
        ];
    }
}
