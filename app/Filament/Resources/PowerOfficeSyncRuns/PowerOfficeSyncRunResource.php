<?php

namespace App\Filament\Resources\PowerOfficeSyncRuns;

use App\Filament\Resources\PowerOfficeSyncRuns\Pages\ListPowerOfficeSyncRuns;
use App\Filament\Resources\PowerOfficeSyncRuns\Tables\PowerOfficeSyncRunsTable;
use App\Models\PowerOfficeSyncRun;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PowerOfficeSyncRunResource extends Resource
{
    protected static ?string $model = PowerOfficeSyncRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationGroup(): ?string
    {
        return 'PowerOffice';
    }

    public static function getModelLabel(): string
    {
        return 'Sync run';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Sync history';
    }

    public static function getNavigationLabel(): string
    {
        return 'Sync history';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        try {
            $tenant = Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                $query->where('store_id', $tenant->getKey());
            }
        } catch (\Throwable) {
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return PowerOfficeSyncRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPowerOfficeSyncRuns::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
