<?php

namespace App\Filament\Resources\ConnectedPaymentMethods;

use App\Filament\Resources\ConnectedPaymentMethods\Pages\CreateConnectedPaymentMethod;
use App\Filament\Resources\ConnectedPaymentMethods\Pages\EditConnectedPaymentMethod;
use App\Filament\Resources\ConnectedPaymentMethods\Pages\ListConnectedPaymentMethods;
use App\Filament\Resources\ConnectedPaymentMethods\Pages\ViewConnectedPaymentMethod;
use App\Filament\Resources\ConnectedPaymentMethods\Schemas\ConnectedPaymentMethodForm;
use App\Filament\Resources\ConnectedPaymentMethods\Schemas\ConnectedPaymentMethodInfolist;
use App\Filament\Resources\ConnectedPaymentMethods\Tables\ConnectedPaymentMethodsTable;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Models\ConnectedPaymentMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedPaymentMethodResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ConnectedPaymentMethod::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'stripe_payment_method_id';

    public static function getLabel(): string
    {
        return 'Payment Method';
    }

    public static function getPluralLabel(): string
    {
        return 'Payment Methods';
    }

    public static function getNavigationLabel(): string
    {
        return 'Payment Methods';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Payments';
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        if (! $record) {
            return null;
        }
        return $record->card_display ?? $record->stripe_payment_method_id;
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectedPaymentMethodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedPaymentMethodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedPaymentMethodsTable::configure($table);
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
            'index' => ListConnectedPaymentMethods::route('/'),
            'create' => CreateConnectedPaymentMethod::route('/create'),
            'view' => ViewConnectedPaymentMethod::route('/{record}'),
            'edit' => EditConnectedPaymentMethod::route('/{record}/edit'),
        ];
    }
}
