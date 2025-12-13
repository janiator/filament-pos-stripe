<?php

namespace App\Filament\Resources\PosDevices;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\PosDevices\Pages\CreatePosDevice;
use App\Filament\Resources\PosDevices\Pages\EditPosDevice;
use App\Filament\Resources\PosDevices\Pages\ListPosDevices;
use App\Filament\Resources\PosDevices\Pages\ViewPosDevice;
use App\Filament\Resources\PosDevices\Schemas\PosDeviceForm;
use App\Filament\Resources\PosDevices\Schemas\PosDeviceInfolist;
use App\Filament\Resources\PosDevices\Tables\PosDevicesTable;
use App\Models\PosDevice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PosDeviceResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = PosDevice::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $recordTitleAttribute = 'device_name';

    public static function getLabel(): string
    {
        return __('filament.resources.pos_device.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.pos_device.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.pos_device.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.terminals');
    }

    public static function form(Schema $schema): Schema
    {
        return PosDeviceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PosDeviceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosDevicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\PosDevices\RelationManagers\TerminalLocationsRelationManager::class,
            \App\Filament\Resources\PosDevices\RelationManagers\ReceiptPrintersRelationManager::class,
            \App\Filament\Resources\PosDevices\RelationManagers\PosSessionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosDevices::route('/'),
            'create' => CreatePosDevice::route('/create'),
            'view' => ViewPosDevice::route('/{record}'),
            'edit' => EditPosDevice::route('/{record}/edit'),
        ];
    }
}

