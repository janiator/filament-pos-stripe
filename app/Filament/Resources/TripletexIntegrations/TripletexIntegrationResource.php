<?php

namespace App\Filament\Resources\TripletexIntegrations;

use App\Enums\AddonType;
use App\Filament\Resources\TripletexIntegrations\Pages\ManageTripletexIntegration;
use App\Filament\Resources\TripletexIntegrations\Schemas\TripletexIntegrationForm;
use App\Models\Addon;
use App\Models\TripletexIntegration;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripletexIntegrationResource extends Resource
{
    protected static ?string $model = TripletexIntegration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function getNavigationGroup(): ?string
    {
        return 'Tripletex';
    }

    public static function getModelLabel(): string
    {
        return 'Tripletex integration';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tripletex';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tripletex';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Tripletex);
    }

    /**
     * Used by manage page authorization (mirrors PowerOffice pattern).
     */
    public static function canAccess(): bool
    {
        return Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Tripletex);
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
        return TripletexIntegrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTripletexIntegration::route('/'),
        ];
    }
}
