<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and return API token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke all existing tokens (optional - for single device login)
        // $user->tokens()->delete();

        // Create a new token
            // Set current store if not already set
            if (!$user->current_store_id && $user->stores()->count() > 0) {
                $firstStore = $user->stores()->first();
                $user->setCurrentStore($firstStore);
            }

            $token = $user->createToken('mobile-app')->plainTextToken;
            $currentStore = $user->currentStore();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
                'current_store' => $currentStore ? [
                    'id' => $currentStore->id,
                    'slug' => $currentStore->slug,
                    'name' => $currentStore->name,
                    'email' => $currentStore->email,
                    'stripe_account_id' => $currentStore->stripe_account_id,
                ] : null,
                'stores' => $user->stores->map(function ($store) use ($currentStore) {
                    return [
                        'id' => $store->id,
                        'slug' => $store->slug,
                        'name' => $store->name,
                        'email' => $store->email,
                        'stripe_account_id' => $store->stripe_account_id,
                        'is_current' => $currentStore && $currentStore->id === $store->id,
                    ];
                }),
            ]);
    }

    /**
     * Get authenticated user information
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentStore = $user->currentStore();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'current_store' => $currentStore ? [
                'id' => $currentStore->id,
                'slug' => $currentStore->slug,
                'name' => $currentStore->name,
                'email' => $currentStore->email,
                'stripe_account_id' => $currentStore->stripe_account_id,
            ] : null,
            'stores' => $user->stores->map(function ($store) use ($currentStore) {
                return [
                    'id' => $store->id,
                    'slug' => $store->slug,
                    'name' => $store->name,
                    'email' => $store->email,
                    'stripe_account_id' => $store->stripe_account_id,
                    'is_current' => $currentStore && $currentStore->id === $store->id,
                ];
            }),
        ]);
    }

    /**
     * Logout user and revoke current token
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ]);
    }
}
