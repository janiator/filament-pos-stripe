<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDynamicAppUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set APP_URL dynamically based on current request
        if ($request->hasHeader('Host')) {
            $scheme = $request->getScheme();
            $host = $request->getHost();
            $port = $request->getPort();
            $baseUrl = $scheme . '://' . $host . ($port && $port != 80 && $port != 443 ? ':' . $port : '');
            
            // Override filesystem public disk URL
            config(['filesystems.disks.public.url' => $baseUrl . '/storage']);
            
            // Force URL root
            \Illuminate\Support\Facades\URL::forceRootUrl($baseUrl);
        }
        
        return $next($request);
    }
}

