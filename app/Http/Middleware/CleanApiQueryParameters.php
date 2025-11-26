<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CleanApiQueryParameters
{
    /**
     * Handle an incoming request.
     * 
     * Removes null, empty string, and whitespace-only query parameters from API requests.
     * This ensures that empty search/filter parameters from FlutterFlow are ignored.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to API routes
        if ($request->is('api/*')) {
            $query = $request->query->all();
            
            // Filter out null, empty strings, and whitespace-only strings
            $cleanedQuery = array_filter($query, function ($value) {
                // Handle arrays (e.g., filters[])
                if (is_array($value)) {
                    // Filter empty values from arrays
                    $filtered = array_filter($value, function ($item) {
                        return $item !== null && $item !== '' && trim($item) !== '';
                    });
                    // Only include array if it has at least one non-empty value
                    return !empty($filtered);
                }
                
                // Handle scalar values (strings, numbers, etc.)
                if ($value === null || $value === '') {
                    return false;
                }
                
                // Remove whitespace-only strings
                if (is_string($value) && trim($value) === '') {
                    return false;
                }
                
                return true;
            });
            
            // Replace the query parameters with cleaned version
            $request->query->replace($cleanedQuery);
        }

        return $next($request);
    }
}
