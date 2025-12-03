<?php

namespace App\Http\Controllers\Api;

use App\Models\Store;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;

abstract class BaseApiController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator, Request $request = null)
    {
        // Always throw ValidationException for API routes - Laravel will return JSON
        throw new ValidationException($validator);
    }

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

        // Fallback to user's current store
        if ($user = $request->user()) {
            return $user->currentStore();
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

    /**
     * Format a date/time in Oslo timezone for API responses
     * 
     * @param \Illuminate\Support\Carbon|\DateTime|null $dateTime
     * @return string|null ISO 8601 formatted string in Oslo timezone
     */
    protected function formatDateTimeOslo($dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        return $dateTime->setTimezone('Europe/Oslo')->toIso8601String();
    }
}
