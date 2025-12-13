<?php

namespace App\Filament\Resources\ReceiptTemplates;

use App\Filament\Resources\ReceiptTemplates\Pages\CreateReceiptTemplate;
use App\Filament\Resources\ReceiptTemplates\Pages\EditReceiptTemplate;
use App\Filament\Resources\ReceiptTemplates\Pages\ListReceiptTemplates;
use App\Filament\Resources\ReceiptTemplates\Schemas\ReceiptTemplateForm;
use App\Filament\Resources\ReceiptTemplates\Tables\ReceiptTemplatesTable;
use App\Models\ReceiptTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReceiptTemplateResource extends Resource
{
    protected static ?string $model = ReceiptTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'template_type';

    // Disable tenant scoping - templates can be global or store-specific
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function boot(): void
    {
        parent::boot();
        static::scopeToTenant(false);
    }

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getLabel(): string
    {
        return __('filament.resources.receipt_template.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.receipt_template.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.receipt_template.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.pos_system');
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function form(Schema $schema): Schema
    {
        return ReceiptTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiptTemplatesTable::configure($table);
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
            'index' => ListReceiptTemplates::route('/'),
            'create' => CreateReceiptTemplate::route('/create'),
            'edit' => EditReceiptTemplate::route('/{record}/edit'),
        ];
    }
}
