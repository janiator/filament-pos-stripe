<?php

namespace App\Filament\Resources\TripletexSyncRuns;

use App\Filament\Resources\TripletexSyncRuns\Pages\ListTripletexSyncRuns;
use App\Filament\Resources\TripletexSyncRuns\Tables\TripletexSyncRunsTable;
use App\Models\TripletexSyncRun;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripletexSyncRunResource extends Resource
{
    protected static ?string $model = TripletexSyncRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationGroup(): ?string
    {
        return 'Tripletex';
    }

    public static function getModelLabel(): string
    {
        return 'Tripletex sync run';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tripletex sync history';
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
        return TripletexSyncRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTripletexSyncRuns::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
