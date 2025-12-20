<?php

namespace App\Filament\Resources\ArticleGroupCodes;

use App\Filament\Resources\ArticleGroupCodes\Pages\CreateArticleGroupCode;
use App\Filament\Resources\ArticleGroupCodes\Pages\EditArticleGroupCode;
use App\Filament\Resources\ArticleGroupCodes\Pages\ListArticleGroupCodes;
use App\Filament\Resources\ArticleGroupCodes\Schemas\ArticleGroupCodeForm;
use App\Filament\Resources\ArticleGroupCodes\Tables\ArticleGroupCodesTable;
use App\Models\ArticleGroupCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ArticleGroupCodeResource extends Resource
{

    protected static ?string $model = ArticleGroupCode::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ArticleGroupCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticleGroupCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getLabel(): string
    {
        return __('filament.resources.article_group_code.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.article_group_code.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.article_group_code.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.catalog');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                // Show store-specific codes and global standard codes
                $query->where(function ($q) use ($tenant) {
                    $q->where('stripe_account_id', $tenant->stripe_account_id)
                      ->orWhere(function ($q2) {
                          $q2->whereNull('stripe_account_id')
                             ->where('is_standard', true);
                      });
                });
            }
        } catch (\Throwable $e) {
            // Fallback if Filament facade not available
        }
        
        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticleGroupCodes::route('/'),
            'create' => CreateArticleGroupCode::route('/create'),
            'edit' => EditArticleGroupCode::route('/{record}/edit'),
        ];
    }
}
