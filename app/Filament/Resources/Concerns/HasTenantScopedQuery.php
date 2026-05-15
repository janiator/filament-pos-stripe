<?php

namespace App\Filament\Resources\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasTenantScopedQuery
{
    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant = null): Builder
    {
        // Don't use automatic tenant scoping - we handle it manually in getEloquentQuery
        return $query;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        try {
            $tenant = Filament::getTenant();
            if (! $tenant || $tenant->slug === 'visivo-admin') {
                return $query;
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = static::getModel();
            $table = (new $modelClass)->getTable();

            if (static::tenantScopesUsingStoreIdColumn()) {
                return $query->where("{$table}.store_id", $tenant->id);
            }

            if (static::tenantScopesUsingStripeAccountId()) {
                // Match ConnectedChargeResource: no extra filter when the tenant has no Stripe account yet
                if (! $tenant->stripe_account_id) {
                    return $query;
                }

                return $query->where("{$table}.stripe_account_id", $tenant->stripe_account_id);
            }

            $query->whereHas('posDevice', function ($q) use ($tenant): void {
                $q->where('store_id', $tenant->id);
            });
        } catch (\Throwable $e) {
            // Fallback if Filament facade not available
        }

        return $query;
    }

    /**
     * When true, scope with `where({table}.store_id, tenant.id)`. Takes precedence over stripe-account scoping.
     */
    protected static function tenantScopesUsingStoreIdColumn(): bool
    {
        return false;
    }

    /**
     * When true (and store-id scoping is off), scope with `where({table}.stripe_account_id, tenant.stripe_account_id)`.
     */
    protected static function tenantScopesUsingStripeAccountId(): bool
    {
        return false;
    }
}
