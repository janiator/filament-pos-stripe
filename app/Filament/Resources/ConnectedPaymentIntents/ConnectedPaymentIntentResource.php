<?php

namespace App\Filament\Resources\ConnectedPaymentIntents;

use App\Filament\Resources\ConnectedPaymentIntents\Pages\ListConnectedPaymentIntents;
use App\Filament\Resources\ConnectedPaymentIntents\Pages\ViewConnectedPaymentIntent;
use App\Filament\Resources\ConnectedPaymentIntents\Schemas\ConnectedPaymentIntentForm;
use App\Filament\Resources\ConnectedPaymentIntents\Schemas\ConnectedPaymentIntentInfolist;
use App\Filament\Resources\ConnectedPaymentIntents\Tables\ConnectedPaymentIntentsTable;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Models\ConnectedPaymentIntent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedPaymentIntentResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ConnectedPaymentIntent::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'stripe_id';

    public static function getLabel(): string
    {
        return __('filament.resources.connected_payment_intent.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.connected_payment_intent.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.connected_payment_intent.navigation');
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
        return $record->formatted_amount . ' - ' . ($record->description ?? $record->stripe_id);
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectedPaymentIntentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedPaymentIntentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedPaymentIntentsTable::configure($table);
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
            'index' => ListConnectedPaymentIntents::route('/'),
            'view' => ViewConnectedPaymentIntent::route('/{record}'),
        ];
    }
}
