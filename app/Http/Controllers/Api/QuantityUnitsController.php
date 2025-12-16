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
            // Exclude global units if a store-specific version with the same name exists
            $query = QuantityUnit::where(function ($q) use ($store) {
                // Include store-specific units
                $q->where('stripe_account_id', $store->stripe_account_id)
                  // Include global standard units that don't have a store-specific version
                  ->orWhere(function ($q2) use ($store) {
                      $q2->whereNull('stripe_account_id')
                         ->where('is_standard', true)
                         ->whereNotExists(function ($subQuery) use ($store) {
                             $subQuery->select(\DB::raw(1))
                                      ->from('quantity_units as q2')
                                      ->whereColumn('q2.name', 'quantity_units.name')
                                      ->where(function ($q3) {
                                          $q3->whereColumn('q2.symbol', 'quantity_units.symbol')
                                             ->orWhere(function ($q4) {
                                                 $q4->whereNull('q2.symbol')
                                                    ->whereNull('quantity_units.symbol');
                                             });
                                      })
                                      ->where('q2.stripe_account_id', $store->stripe_account_id)
                                      ->where('q2.active', true);
                         });
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

            // Transform quantity units and deduplicate by name+symbol
            // Prefer store-specific units over global standard units
            $unitsByNameSymbol = [];
            $transformedUnits = $quantityUnits->getCollection()->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'description' => $unit->description,
                    'is_standard' => $unit->is_standard,
                    'active' => $unit->active,
                    'stripe_account_id' => $unit->stripe_account_id, // Include for client-side deduplication
                    'display_name' => $unit->name . ($unit->symbol ? ' (' . $unit->symbol . ')' : ''),
                    'created_at' => $this->formatDateTimeOslo($unit->created_at),
                    'updated_at' => $this->formatDateTimeOslo($unit->updated_at),
                ];
            })->filter(function ($unit) use (&$unitsByNameSymbol) {
                // Deduplicate by name+symbol combination
                $key = strtolower($unit['name'] . '|' . ($unit['symbol'] ?? ''));
                
                if (!isset($unitsByNameSymbol[$key])) {
                    $unitsByNameSymbol[$key] = $unit;
                    return true;
                }
                
                // If we already have a unit with this name+symbol, prefer store-specific over global
                $existing = $unitsByNameSymbol[$key];
                $existingIsStoreSpecific = !empty($existing['stripe_account_id']);
                $currentIsStoreSpecific = !empty($unit['stripe_account_id']);
                
                // Replace if current is store-specific and existing is global
                if ($currentIsStoreSpecific && !$existingIsStoreSpecific) {
                    $unitsByNameSymbol[$key] = $unit;
                    return true;
                }
                
                // Otherwise, keep the existing one
                return false;
            })->values();

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

