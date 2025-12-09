<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Resources\Settings\Schemas\SettingsForm;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Models\Setting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SettingsResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = Setting::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $recordTitleAttribute = 'store_id';

    public static function getLabel(): string
    {
        return __('filament.resources.settings.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.settings.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.settings.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.administration');
    }

    public static function form(Schema $schema): Schema
    {
        return SettingsForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSettings::route('/'),
        ];
    }

    public static function getNavigationUrl(): string
    {
        // For singleton, navigate to index which will handle the singleton
        return static::getUrl('index');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                // Scope to current store
                $query->where('store_id', $tenant->id);
            }
        } catch (\Throwable $e) {
            // Fallback if Filament facade not available
        }
        
        return $query;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
