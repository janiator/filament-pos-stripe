<?php

namespace App\Filament\Resources\TerminalReaders;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\TerminalReaders\Pages\CreateTerminalReader;
use App\Filament\Resources\TerminalReaders\Pages\EditTerminalReader;
use App\Filament\Resources\TerminalReaders\Pages\ListTerminalReaders;
use App\Filament\Resources\TerminalReaders\Pages\ViewTerminalReader;
use App\Filament\Resources\TerminalReaders\Schemas\TerminalReaderForm;
use App\Filament\Resources\TerminalReaders\Schemas\TerminalReaderInfolist;
use App\Filament\Resources\TerminalReaders\Tables\TerminalReadersTable;
use App\Models\TerminalReader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TerminalReaderResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = TerminalReader::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $recordTitleAttribute = 'label';

    public static function getLabel(): string
    {
        return __('filament.resources.terminal_reader.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.terminal_reader.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.terminal_reader.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.terminals_and_equipment');
    }

    public static function form(Schema $schema): Schema
    {
        return TerminalReaderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TerminalReaderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TerminalReadersTable::configure($table);
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
            'index' => ListTerminalReaders::route('/'),
            'create' => CreateTerminalReader::route('/create'),
            'view' => ViewTerminalReader::route('/{record}'),
            'edit' => EditTerminalReader::route('/{record}/edit'),
        ];
    }
}
