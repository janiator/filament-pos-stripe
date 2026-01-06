<?php

namespace App\Filament\Resources\WebhookLogs;

use App\Filament\Resources\WebhookLogs\Pages\ListWebhookLogs;
use App\Filament\Resources\WebhookLogs\Schemas\WebhookLogForm;
use App\Filament\Resources\WebhookLogs\Tables\WebhookLogsTable;
use App\Models\WebhookLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebhookLogResource extends Resource
{
    protected static ?string $model = WebhookLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    protected static ?int $navigationSort = 100;

    // Disable tenant scoping - Webhook logs are for super admins only
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function boot(): void
    {
        parent::boot();
        
        // Disable tenant scoping for webhook logs
        static::scopeToTenant(false);
    }

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        // Only allow super admins to view webhook logs
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        
        return \Filament\Facades\Filament::getTenant() 
            ? $user->roles()->withoutGlobalScopes()->where('name', 'super_admin')->exists()
            : $user->hasRole('super_admin');
    }

    public static function canCreate(): bool
    {
        // Webhook logs are created automatically, not manually
        return false;
    }

    public static function canEdit($record): bool
    {
        // Webhook logs are read-only
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Bypass tenant scoping
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function infolist(Schema $schema): Schema
    {
        return WebhookLogForm::configure($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return WebhookLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebhookLogsTable::configure($table);
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
            'index' => ListWebhookLogs::route('/'),
            'view' => \App\Filament\Resources\WebhookLogs\Pages\ViewWebhookLog::route('/{record}'),
        ];
    }
}
