<?php

namespace App\Filament\Resources\PosEvents;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\PosEvents\Pages\CreatePosEvent;
use App\Filament\Resources\PosEvents\Pages\EditPosEvent;
use App\Filament\Resources\PosEvents\Pages\ListPosEvents;
use App\Filament\Resources\PosEvents\Schemas\PosEventForm;
use App\Filament\Resources\PosEvents\Tables\PosEventsTable;
use App\Models\PosEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PosEventResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = PosEvent::class;

    // Disable automatic tenant scoping - we'll handle it manually via trait
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'POS Events';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'POS System';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function form(Schema $schema): Schema
    {
        return PosEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PosEventsTable::configure($table);
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
            'index' => ListPosEvents::route('/'),
            'create' => CreatePosEvent::route('/create'),
            'edit' => EditPosEvent::route('/{record}/edit'),
        ];
    }
}
