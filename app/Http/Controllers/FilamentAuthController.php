<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class FilamentAuthController extends Controller
{
    /**
     * Authenticate user via token and redirect to Filament panel
     * 
     * This route accepts a Sanctum token and creates a web session
     * for the user, then redirects them to the Filament admin panel.
     * 
     * Usage: /filament-auth/{token}?store={store_slug}
     * 
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function authenticate(Request $request, string $token)
    {
        // Find the token and get the user
        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Invalid or expired token',
                ], 401);
            }
            
            return redirect('/login')->with('error', 'Invalid or expired token');
        }
        
        $user = $accessToken->tokenable;
        
        // Determine which panel to use (embed or main)
        $useEmbedPanel = $request->query('embed') || $request->query('minimal');
        $panelId = $useEmbedPanel ? 'embed' : 'app';
        
        // Get Filament panel instance
        $panel = Filament::getPanel($panelId);
        
        // Check if user can access Filament panel
        if (!$user->canAccessPanel($panel)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'User does not have access to the Filament panel',
                ], 403);
            }
            
            return redirect('/login')->with('error', 'You do not have access to the admin panel');
        }
        
        // Log in the user via session
        Auth::login($user, true); // true = remember me
        
        // Get available stores/tenants for the user
        $stores = $user->getTenants($panel);
        
        if ($stores->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'User has no accessible stores',
                ], 403);
            }
            
            return redirect('/login')->with('error', 'You do not have access to any stores');
        }
        
        // Determine which store/tenant to use
        $storeSlug = $request->query('store');
        $store = null;
        
        if ($storeSlug) {
            // Use requested store if user has access
            $store = $stores->firstWhere('slug', $storeSlug);
            if (!$store) {
                // Fall back to current store or first store
                $store = $user->currentStore() ?? $stores->first();
            }
        } else {
            // Use user's current store or first available
            $store = $user->currentStore() ?? $stores->first();
        }
        
        // Use embed panel if embed parameter is present, otherwise use main panel
        $useEmbedPanel = $request->query('embed') || $request->query('minimal');
        $panelPath = $useEmbedPanel ? 'embed' : 'app';
        
        // Build Filament panel URL with tenant
        $panelUrl = '/' . $panelPath . '/store/' . $store->slug;
        
        // If there's a redirect parameter, append it
        if ($redirect = $request->query('redirect')) {
            $panelUrl .= '/' . ltrim($redirect, '/');
        }
        
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Authentication successful',
                'redirect_url' => $panelUrl,
                'store' => [
                    'id' => $store->id,
                    'slug' => $store->slug,
                    'name' => $store->name,
                ],
            ]);
        }
        
        // Redirect to Filament panel
        return redirect($panelUrl);
    }
}




