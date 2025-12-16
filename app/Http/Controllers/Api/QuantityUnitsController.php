<?php

namespace App\Http\Controllers\Api;

use App\Models\QuantityUnit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuantityUnitsController extends BaseApiController
{
    /**
     * Display a listing of quantity units
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $store = $this->getTenantStore($request);
            
            if (!$store) {
                return response()->json(['error' => 'Store not found'], 404);
            }

            $this->authorizeTenant($request, $store);

            // Build query - get store-specific units and global standard units
            $query = QuantityUnit::where(function ($q) use ($store) {
                // Include store-specific units and global standard units
                $q->where('stripe_account_id', $store->stripe_account_id)
                  ->orWhere(function ($q2) {
                      $q2->whereNull('stripe_account_id')
                         ->where('is_standard', true);
                  });
            });

            // Filter by active status if provided
            if ($request->has('active')) {
                $query->where('active', filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN));
            } else {
                // Default to active only
                $query->where('active', true);
            }

            // Filter by search term if provided
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('symbol', 'ilike', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            // Get paginated results with distinct to avoid duplicates
            $perPage = min($request->get('per_page', 100), 100); // Max 100 per page
            $quantityUnits = $query->distinct()
                ->orderBy('is_standard', 'desc') // Standard units first
                ->orderBy('name')
                ->paginate($perPage);

            // Transform quantity units
            $transformedUnits = $quantityUnits->getCollection()->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'description' => $unit->description,
                    'is_standard' => $unit->is_standard,
                    'active' => $unit->active,
                    'display_name' => $unit->name . ($unit->symbol ? ' (' . $unit->symbol . ')' : ''),
                    'created_at' => $this->formatDateTimeOslo($unit->created_at),
                    'updated_at' => $this->formatDateTimeOslo($unit->updated_at),
                ];
            });

            return response()->json([
                'quantity_units' => $transformedUnits,
                'meta' => [
                    'current_page' => $quantityUnits->currentPage(),
                    'last_page' => $quantityUnits->lastPage(),
                    'per_page' => $quantityUnits->perPage(),
                    'total' => $quantityUnits->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Error in QuantityUnitsController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
