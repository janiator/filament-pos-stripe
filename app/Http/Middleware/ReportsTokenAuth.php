<?php

namespace App\Http\Middleware;

use App\Enums\AddonType;
use App\Models\Addon;
use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportsTokenAuth
{
    /**
     * Authenticate using a per-store reports API token sent as Bearer + X-Tenant header.
     * Only accepts the token when the store has the MeranoBooking add-on active.
     * Falls through to Sanctum if no reports token is present, so the endpoint
     * remains usable by authenticated Sanctum users as well.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        $tenantSlug = $request->header('X-Tenant');

        if ($bearer && $tenantSlug) {
            $store = Store::where('slug', $tenantSlug)->first();

            if (
                $store
                && Addon::storeHasActiveAddon($store->id, AddonType::MeranoBooking)
                && filled($store->reports_api_token)
                && hash_equals($store->reports_api_token, $bearer)
            ) {
                $request->attributes->set('reports_token_store', $store);

                return $next($request);
            }
        }

        return app(\Illuminate\Auth\Middleware\Authenticate::class)->handle(
            $request,
            $next,
            'sanctum',
        );
    }
}
