<?php

namespace App\Http\Controllers\Api;

use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends BaseApiController
{
    /**
     * Get all stores accessible by the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Super admins can see all stores
        if ($user->hasRole('super_admin')) {
            $stores = Store::all();
        } else {
            $stores = $user->stores;
        }

        return response()->json([
            'stores' => $stores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'slug' => $store->slug,
                    'name' => $store->name,
                    'email' => $store->email,
                    'stripe_account_id' => $store->stripe_account_id,
                    'commission_type' => $store->commission_type,
                    'commission_rate' => $store->commission_rate,
                ];
            }),
        ]);
    }

    /**
     * Get a specific store by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $store = Store::where('slug', $slug)->first();

        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        // Check if user has access to this store
        if (!$user->hasRole('super_admin') && !$user->stores->contains($store)) {
            return response()->json([
                'message' => 'You do not have access to this store',
            ], 403);
        }

        return response()->json([
            'store' => [
                'id' => $store->id,
                'slug' => $store->slug,
                'name' => $store->name,
                'email' => $store->email,
                'stripe_account_id' => $store->stripe_account_id,
                'commission_type' => $store->commission_type,
                'commission_rate' => $store->commission_rate,
            ],
        ]);
    }

    /**
     * Get the current user's default store
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->currentStore();

        if (!$store) {
            return response()->json([
                'message' => 'No store assigned to this user',
            ], 404);
        }

        return response()->json([
            'store' => [
                'id' => $store->id,
                'slug' => $store->slug,
                'name' => $store->name,
                'email' => $store->email,
                'stripe_account_id' => $store->stripe_account_id,
                'commission_type' => $store->commission_type,
                'commission_rate' => $store->commission_rate,
            ],
        ]);
    }

    /**
     * Change the current user's default store
     */
    public function updateCurrent(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validate the request - only one is required (either store_id OR store_slug)
        $validated = $request->validate([
            'store_id' => 'required_without:store_slug|integer|exists:stores,id',
            'store_slug' => 'required_without:store_id|string|exists:stores,slug',
        ], [
            'store_id.required_without' => 'Either store_id or store_slug is required.',
            'store_slug.required_without' => 'Either store_id or store_slug is required.',
        ]);

        // Get the store to set as current
        if (isset($validated['store_id'])) {
            $store = Store::find($validated['store_id']);
        } else {
            $store = Store::where('slug', $validated['store_slug'])->first();
        }

        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        // Verify user has access to this store
        if (!$user->hasRole('super_admin') && !$user->stores->contains($store)) {
            return response()->json([
                'message' => 'You do not have access to this store',
            ], 403);
        }

        // Set as current store
        if (!$user->setCurrentStore($store)) {
            return response()->json([
                'message' => 'Failed to set current store',
            ], 500);
        }

        return response()->json([
            'message' => 'Current store changed successfully',
            'store' => [
                'id' => $store->id,
                'slug' => $store->slug,
                'name' => $store->name,
                'email' => $store->email,
                'stripe_account_id' => $store->stripe_account_id,
                'commission_type' => $store->commission_type,
                'commission_rate' => $store->commission_rate,
            ],
        ]);
    }
}
