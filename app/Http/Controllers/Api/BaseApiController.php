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
     * Format a date/time in Oslo timezone for Flutter-compatible API responses
     * Returns ISO 8601 format with Oslo timezone offset (e.g., "2025-12-05T13:50:14.000+01:00")
     * Flutter's DateTime.parse() can handle timezone offsets.
     * 
     * @param \Illuminate\Support\Carbon|\DateTime|string|null $dateTime
     * @return string|null ISO 8601 formatted string in Oslo timezone
     */
    protected function formatDateTimeOslo($dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        // Handle string input (e.g., from JSON cached data)
        if (is_string($dateTime)) {
            try {
                $dateTime = \Carbon\Carbon::parse($dateTime);
            } catch (\Exception $e) {
                // If parsing fails, return null
                return null;
            }
        }
        // Convert to Carbon if not already
        elseif (!$dateTime instanceof \Carbon\Carbon) {
            $dateTime = \Carbon\Carbon::instance($dateTime);
        }

        // Convert to Oslo timezone
        $oslo = $dateTime->copy()->setTimezone('Europe/Oslo');
        
        // Format as ISO 8601 with milliseconds and timezone offset for Flutter compatibility
        // Format: "2025-12-05T13:50:14.000+01:00" (or +02:00 during DST)
        // Extract milliseconds from microseconds
        $microseconds = $oslo->micro;
        $milliseconds = str_pad((string) floor($microseconds / 1000), 3, '0', STR_PAD_LEFT);
        
        // Get timezone offset in format +HH:MM or -HH:MM
        $offset = $oslo->format('P'); // P format gives +01:00 or +02:00
        
        // Format: YYYY-MM-DDTHH:mm:ss.sss+HH:MM
        return $oslo->format('Y-m-d\TH:i:s') . '.' . $milliseconds . $offset;
    }
}
