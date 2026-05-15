<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Resources\Settings\Schemas\SettingsForm;
use App\Models\Setting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SettingsResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $model = Setting::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static function tenantScopesUsingStoreIdColumn(): bool
    {
        return true;
    }

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
        return __('filament.navigation_groups.settings');
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

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
