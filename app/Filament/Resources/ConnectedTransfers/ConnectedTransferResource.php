<?php

namespace App\Filament\Resources\ConnectedTransfers;

use App\Filament\Resources\ConnectedTransfers\Pages\CreateConnectedTransfer;
use App\Filament\Resources\ConnectedTransfers\Pages\EditConnectedTransfer;
use App\Filament\Resources\ConnectedTransfers\Pages\ListConnectedTransfers;
use App\Filament\Resources\ConnectedTransfers\Pages\ViewConnectedTransfer;
use App\Filament\Resources\ConnectedTransfers\Schemas\ConnectedTransferForm;
use App\Filament\Resources\ConnectedTransfers\Schemas\ConnectedTransferInfolist;
use App\Filament\Resources\ConnectedTransfers\Tables\ConnectedTransfersTable;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Models\ConnectedTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ConnectedTransferResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ConnectedTransfer::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected static ?string $recordTitleAttribute = 'stripe_transfer_id';

    public static function getLabel(): string
    {
        return __('filament.resources.connected_transfer.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.connected_transfer.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.connected_transfer.navigation');
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
        return $record->formatted_amount . ' - ' . ($record->description ?? $record->stripe_transfer_id);
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectedTransferForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConnectedTransferInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectedTransfersTable::configure($table);
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
            'index' => ListConnectedTransfers::route('/'),
            'create' => CreateConnectedTransfer::route('/create'),
            'view' => ViewConnectedTransfer::route('/{record}'),
            'edit' => EditConnectedTransfer::route('/{record}/edit'),
        ];
    }
}
