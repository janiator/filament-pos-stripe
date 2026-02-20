<?php

namespace Positiv\FilamentWebflow\Filament\Resources;

use App\Enums\AddonType;
use App\Models\Addon;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\Pages\CreateWebflowSite;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\Pages\EditWebflowSite;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\Pages\ListWebflowSites;
use Positiv\FilamentWebflow\Filament\Resources\WebflowSiteResource\RelationManagers\CollectionsRelationManager;
use Positiv\FilamentWebflow\Models\WebflowSite;
use UnitEnum;

class WebflowSiteResource extends Resource
{
    protected static ?string $model = WebflowSite::class;

    protected static string|UnitEnum|null $navigationGroup = 'Webflow CMS';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $slug = 'webflow-sites';

    public static function getModelLabel(): string
    {
        return __('filament-webflow::webflow.site');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-webflow::webflow.sites');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-webflow::webflow.sites');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            return false;
        }

        return Addon::query()
            ->where('store_id', $tenant->getKey())
            ->where('is_active', true)
            ->whereIn('type', AddonType::typesWithWebflow())
            ->exists();
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();
        if ($tenant) {
            $query->where('store_id', $tenant->getKey());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\TextInput::make('webflow_site_id')
                    ->label('Webflow Site ID')
                    ->required()
                    ->maxLength(255)
                    ->unique(table: 'webflow_sites', column: 'webflow_site_id', ignoreRecord: true),
                \Filament\Forms\Components\TextInput::make('api_token')
                    ->label('API Token')
                    ->password()
                    ->placeholder(fn ($record) => $record ? 'Leave blank to keep current token' : null)
                    ->maxLength(65535)
                    ->dehydrated(fn ($state) => filled($state)),
                \Filament\Forms\Components\TextInput::make('domain')
                    ->label('Domain')
                    ->maxLength(255),
                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name'),
                \Filament\Tables\Columns\TextColumn::make('webflow_site_id'),
                \Filament\Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CollectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebflowSites::route('/'),
            'create' => CreateWebflowSite::route('/create'),
            'edit' => EditWebflowSite::route('/{record}/edit'),
        ];
    }
}
