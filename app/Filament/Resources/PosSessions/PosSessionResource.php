<?php

namespace App\Filament\Resources\PosSessions;

use App\Filament\Resources\PosSessions\Pages\CreatePosSession;
use App\Filament\Resources\PosSessions\Pages\EditPosSession;
use App\Filament\Resources\PosSessions\Pages\ListPosSessions;
use App\Filament\Resources\PosSessions\Schemas\PosSessionForm;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PosSessionResource extends Resource
{
    protected static ?string $model = PosSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'POS Sessions';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'POS System';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return PosSessionForm::configure($schema);
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
            'edit' => EditPosSession::route('/{record}/edit'),
        ];
    }
}
