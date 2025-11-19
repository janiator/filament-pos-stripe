<?php

namespace App\Http\Controllers\Api;

use App\Models\Team;
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
    protected function getTenant(Request $request): ?Team
    {
        // Get tenant from route parameter or user's current team
        $tenantSlug = $request->route('tenant') ?? $request->header('X-Tenant');
        
        if ($tenantSlug) {
            return Team::where('slug', $tenantSlug)->first();
        }

        // Fallback to user's first team
        if ($user = $request->user()) {
            return $user->teams()->first();
        }

        return null;
    }

    /**
     * Get the store for the current tenant
     */
    protected function getTenantStore(Request $request)
    {
        $tenant = $this->getTenant($request);
        
        if (!$tenant) {
            return null;
        }

        return $tenant->store;
    }

    /**
     * Ensure the user has access to the tenant
     */
    protected function authorizeTenant(Request $request, Team $tenant): void
    {
        $user = $request->user();
        
        if (!$user || !$user->teams->contains($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }
    }
}
