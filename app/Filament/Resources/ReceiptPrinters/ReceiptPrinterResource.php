<?php

namespace App\Filament\Resources\ReceiptPrinters;

use App\Enums\AddonType;
use App\Filament\Resources\Concerns\HasTenantScopedQuery;
use App\Filament\Resources\ReceiptPrinters\Pages\CreateReceiptPrinter;
use App\Filament\Resources\ReceiptPrinters\Pages\EditReceiptPrinter;
use App\Filament\Resources\ReceiptPrinters\Pages\ListReceiptPrinters;
use App\Filament\Resources\ReceiptPrinters\Schemas\ReceiptPrinterForm;
use App\Filament\Resources\ReceiptPrinters\Tables\ReceiptPrintersTable;
use App\Models\ReceiptPrinter;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReceiptPrinterResource extends Resource
{
    use HasTenantScopedQuery;

    protected static ?string $model = ReceiptPrinter::class;

    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getLabel(): string
    {
        return __('filament.resources.receipt_printer.label');
    }

    public static function getPluralLabel(): string
    {
        return __('filament.resources.receipt_printer.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.resources.receipt_printer.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation_groups.terminals_and_equipment');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Models\Addon::storeHasActiveAddon(Filament::getTenant()?->getKey(), AddonType::Pos);
    }

    public static function form(Schema $schema): Schema
    {
        return ReceiptPrinterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiptPrintersTable::configure($table);
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
            'index' => ListReceiptPrinters::route('/'),
            'create' => CreateReceiptPrinter::route('/create'),
            'edit' => EditReceiptPrinter::route('/{record}/edit'),
        ];
    }
}
