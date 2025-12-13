<?php

namespace App\Filament\Resources\ProductDeclarations;

use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\ProductDeclarations\Pages\CreateProductDeclaration;
use App\Filament\Resources\ProductDeclarations\Pages\EditProductDeclaration;
use App\Filament\Resources\ProductDeclarations\Pages\ListProductDeclarations;
use App\Filament\Resources\ProductDeclarations\Schemas\ProductDeclarationForm;
use App\Filament\Resources\ProductDeclarations\Tables\ProductDeclarationsTable;
use App\Models\ProductDeclaration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductDeclarationResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ProductDeclaration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function getNavigationLabel(): string
    {
        return 'Produktfråsegn';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.pos_system');
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public static function form(Schema $schema): Schema
    {
        return ProductDeclarationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductDeclarationsTable::configure($table);
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
            'index' => ListProductDeclarations::route('/'),
            'create' => CreateProductDeclaration::route('/create'),
            'edit' => EditProductDeclaration::route('/{record}/edit'),
        ];
    }
}
