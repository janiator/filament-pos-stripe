<?php

namespace App\Filament\Resources\Concerns;

trait HasTenantScopedQuery
{
    public static function scopeEloquentQueryToTenant(\Illuminate\Database\Eloquent\Builder $query, ?\Illuminate\Database\Eloquent\Model $tenant = null): \Illuminate\Database\Eloquent\Builder
    {
        // Don't use automatic tenant scoping - we handle it manually in getEloquentQuery
        return $query;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        
        try {
            $tenant = \Filament\Facades\Filament::getTenant();
            if ($tenant && $tenant->slug !== 'visivo-admin') {
                // Scope to current store
                $query->whereHas('store', function ($q) use ($tenant) {
                    $q->where('stores.id', $tenant->id);
                });
            }
        } catch (\Throwable $e) {
            // Fallback if Filament facade not available
        }
        
        return $query;
    }
}

