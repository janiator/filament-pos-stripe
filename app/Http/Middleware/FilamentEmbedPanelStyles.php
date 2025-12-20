<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilamentEmbedPanelStyles
{
    /**
     * Handle an incoming request.
     * 
     * This middleware injects CSS to hide navigation elements in the embed panel.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only process HTML responses for embed panel
        if (!$response instanceof \Illuminate\Http\Response || 
            !str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return $response;
        }
        
        // Only apply to embed panel routes
        if (!str_starts_with($request->path(), 'embed/')) {
            return $response;
        }
        
        $content = $response->getContent();
        
        // Inject CSS to hide navigation elements
        $embedStyles = view('filament.embed.styles')->render();
        
        // Inject styles before closing head tag
        if (strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', $embedStyles . '</head>', $content);
        } else {
            $content = $embedStyles . $content;
        }
        
        $response->setContent($content);
        
        return $response;
    }
}



