<?php

namespace App\Filament\Resources\StoreStripeBalanceTransactions;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\StoreStripeBalanceTransactions\Pages\ListStoreStripeBalanceTransactions;
use App\Filament\Resources\StoreStripeBalanceTransactions\Pages\ViewStoreStripeBalanceTransaction;
use App\Filament\Resources\StoreStripeBalanceTransactions\Schemas\StoreStripeBalanceTransactionInfolist;
use App\Filament\Resources\StoreStripeBalanceTransactions\Tables\StoreStripeBalanceTransactionsTable;
use App\Models\StoreStripeBalanceTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StoreStripeBalanceTransactionResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = StoreStripeBalanceTransaction::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $recordTitleAttribute = 'stripe_balance_transaction_id';

    public static function getLabel(): string
    {
        return __('filament.resources.store_stripe_balance_transaction.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.store_stripe_balance_transaction.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.store_stripe_balance_transaction.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.payments');
    }

    public static function getNavigationSort(): ?int
    {
        return 46;
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
        return StoreStripeBalanceTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreStripeBalanceTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreStripeBalanceTransactions::route('/'),
            'view' => ViewStoreStripeBalanceTransaction::route('/{record}'),
        ];
    }
}
