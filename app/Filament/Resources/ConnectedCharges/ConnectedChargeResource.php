<?php

namespace App\Filament\Resources\ConnectedCharges;

use App\Filament\Resources\ConnectedCharges\Pages\CreateConnectedCharge;
use App\Filament\Resources\ConnectedCharges\Pages\EditConnectedCharge;
use App\Filament\Resources\ConnectedCharges\Pages\ListConnectedCharges;
use App\Filament\Resources\ConnectedCharges\Pages\ViewConnectedCharge;
use App\Filament\Resources\ConnectedCharges\Schemas\ConnectedChargeForm;
use App\Filament\Resources\ConnectedCharges\Schemas\ConnectedChargeInfolist;
use App\Filament\Resources\ConnectedCharges\Tables\ConnectedChargesTable;
use App\Models\ConnectedCharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedChargeResource extends Resource
{
    protected static ?string $model = ConnectedCharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'stripe_charge_id';

    public static function getLabel(): string
    {
        return 'Charge';
    }

    public static function getPluralLabel(): string
    {
        return 'Charges';
    }

    public static function getNavigationLabel(): string
    {
        return 'Charges';
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
