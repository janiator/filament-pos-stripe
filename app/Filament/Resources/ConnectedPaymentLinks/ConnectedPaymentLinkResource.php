<?php

namespace App\Filament\Resources\ConnectedPaymentLinks;

use App\Filament\Resources\ConnectedPaymentLinks\Pages\CreateConnectedPaymentLink;
use App\Filament\Resources\ConnectedPaymentLinks\Pages\EditConnectedPaymentLink;
use App\Filament\Resources\ConnectedPaymentLinks\Pages\ListConnectedPaymentLinks;
use App\Filament\Resources\ConnectedPaymentLinks\Pages\ViewConnectedPaymentLink;
use App\Filament\Resources\ConnectedPaymentLinks\Schemas\ConnectedPaymentLinkForm;
use App\Filament\Resources\ConnectedPaymentLinks\Schemas\ConnectedPaymentLinkInfolist;
use App\Filament\Resources\ConnectedPaymentLinks\Tables\ConnectedPaymentLinksTable;
use App\Models\ConnectedPaymentLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedPaymentLinkResource extends Resource
{
    protected static ?string $model = ConnectedPaymentLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        if (! $record) {
            return null;
        }
        return $record->name ?? $record->url ?? $record->stripe_payment_link_id;
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectedPaymentLinkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedPaymentLinkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedPaymentLinksTable::configure($table);
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
            'index' => ListConnectedPaymentLinks::route('/'),
            'create' => CreateConnectedPaymentLink::route('/create'),
            'view' => ViewConnectedPaymentLink::route('/{record}'),
            'edit' => EditConnectedPaymentLink::route('/{record}/edit'),
        ];
    }
}
