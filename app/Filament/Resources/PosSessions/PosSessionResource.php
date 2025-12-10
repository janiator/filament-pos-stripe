<?php

namespace App\Filament\Resources\PosSessions;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\PosSessions\Pages\CreatePosSession;
use App\Filament\Resources\PosSessions\Pages\EditPosSession;
use App\Filament\Resources\PosSessions\Pages\EmbedPosSessions;
use App\Filament\Resources\PosSessions\Pages\ListPosSessions;
use App\Filament\Resources\PosSessions\Pages\ViewPosSession;
use App\Filament\Resources\PosSessions\Schemas\PosSessionForm;
use App\Filament\Resources\PosSessions\Schemas\PosSessionInfolist;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PosSessionResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = PosSession::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.pos_session.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.pos_system');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return PosSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PosSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Relation managers can be added later if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosSessions::route('/'),
            'create' => CreatePosSession::route('/create'),
            'view' => ViewPosSession::route('/{record}'),
            'edit' => EditPosSession::route('/{record}/edit'),
            'embed' => EmbedPosSessions::route('/embed'),
        ];
    }
}
