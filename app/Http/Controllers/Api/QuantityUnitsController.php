<?php

namespace App\Http\Controllers\Api;

use App\Models\QuantityUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuantityUnitsController extends BaseApiController
{
    /**
     * Display a listing of quantity units
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $store = $this->getTenantStore($request);

            if (! $store) {
                return response()->json(['error' => 'Store not found'], 404);
            }

            $this->authorizeTenant($request, $store);

            $query = QuantityUnit::query()->visibleInCatalog($store->id);

            if ($request->has('active')) {
                $query->where('active', filter_var($request->get('active'), FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('symbol', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            }

            $perPage = min($request->get('per_page', 100), 100);
            $page = max(1, (int) $request->get('page', 0) + 1);
            $quantityUnits = $query->paginate($perPage, ['*'], 'page', $page);

            $transformedUnits = $quantityUnits->getCollection()->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'description' => $unit->description,
                    'is_standard' => $unit->is_standard,
                    'active' => $unit->active,
                    'stripe_account_id' => $unit->stripe_account_id,
                    'display_name' => $unit->display_name,
                    'created_at' => $this->formatDateTimeOslo($unit->created_at),
                    'updated_at' => $this->formatDateTimeOslo($unit->updated_at),
                ];
            })->values();

            return response()->json([
                'quantity_units' => $transformedUnits,
                'meta' => [
                    'current_page' => $quantityUnits->currentPage() - 1,
                    'last_page' => $quantityUnits->lastPage() - 1,
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
