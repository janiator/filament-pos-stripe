<?php

namespace App\Filament\Resources\TerminalLocations;

use App\Enums\AddonType;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\TerminalLocations\Pages\CreateTerminalLocation;
use App\Filament\Resources\TerminalLocations\Pages\EditTerminalLocation;
use App\Filament\Resources\TerminalLocations\Pages\ListTerminalLocations;
use App\Filament\Resources\TerminalLocations\Pages\ViewTerminalLocation;
use App\Filament\Resources\TerminalLocations\Schemas\TerminalLocationForm;
use App\Filament\Resources\TerminalLocations\Schemas\TerminalLocationInfolist;
use App\Filament\Resources\TerminalLocations\Tables\TerminalLocationsTable;
use App\Models\TerminalLocation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TerminalLocationResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = TerminalLocation::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getLabel(): string
    {
        return __('filament.resources.terminal_location.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.terminal_location.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.terminal_location.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.terminals_and_equipment');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Models\Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Pos);
    }

    public static function form(Schema $schema): Schema
    {
        return TerminalLocationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TerminalLocationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TerminalLocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\TerminalLocations\RelationManagers\TerminalReadersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerminalLocations::route('/'),
            'create' => CreateTerminalLocation::route('/create'),
            'view' => ViewTerminalLocation::route('/{record}'),
            'edit' => EditTerminalLocation::route('/{record}/edit'),
        ];
    }
}
