<?php

namespace App\Http\Controllers\Api;

use App\Models\Store;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class BaseApiController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Get the current tenant from the request
     */
    protected function getTenant(Request $request): ?Store
    {
        // Get tenant from route parameter or user's current store
        $tenantSlug = $request->route('tenant') ?? $request->header('X-Tenant');
        
        if ($tenantSlug) {
            return Store::where('slug', $tenantSlug)->first();
        }

        // Fallback to user's first store
        if ($user = $request->user()) {
            return $user->stores()->first();
        }

        return null;
    }

    /**
     * Get the store for the current tenant (tenant IS the store now)
     */
    protected function getTenantStore(Request $request)
    {
        return $this->getTenant($request);
    }

    /**
     * Ensure the user has access to the tenant
     */
    protected function authorizeTenant(Request $request, Store $tenant): void
    {
        $user = $request->user();
        
        if (!$user || !$user->stores->contains($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }
    }
}
