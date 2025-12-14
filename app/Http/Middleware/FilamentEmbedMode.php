<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FilamentEmbedMode
{
    /**
     * Handle an incoming request.
     * 
     * This middleware detects embed mode via query parameter and
     * injects CSS/JavaScript to hide navigation and other UI elements.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Check if embed mode is requested
        if (!$request->query('embed') && !$request->query('minimal')) {
            return $response;
        }
        
        // Only process HTML responses
        if (!$response instanceof \Illuminate\Http\Response || 
            !str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return $response;
        }
        
        $content = $response->getContent();
        
        // Inject CSS and JavaScript to hide navigation and other UI elements
        $embedStyles = '
        <style id="filament-embed-styles">
            /* Hide sidebar navigation */
            .fi-sidebar,
            [data-sidebar],
            aside[class*="sidebar"],
            nav[class*="sidebar"] {
                display: none !important;
            }
            
            /* Hide top navigation/header */
            .fi-topbar,
            [data-topbar],
            header[class*="topbar"],
            header[class*="header"] {
                display: none !important;
            }
            
            /* Make main content full width */
            .fi-main,
            [data-main],
            main[class*="main"] {
                margin-left: 0 !important;
                padding-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            /* Hide account widget and other header widgets */
            .fi-account-widget,
            .fi-user-menu,
            [data-user-menu] {
                display: none !important;
            }
            
            /* Remove padding/margins from page container */
            .fi-page,
            [data-page] {
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* Full width for resource pages */
            .fi-resource,
            [data-resource] {
                padding: 1rem !important;
            }
            
            /* Hide breadcrumbs if present */
            .fi-breadcrumbs,
            [data-breadcrumbs],
            nav[aria-label="breadcrumb"] {
                display: none !important;
            }
            
            /* Ensure body has no extra padding */
            body {
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* Hide any footer elements */
            footer {
                display: none !important;
            }
        </style>
        ';
        
        $embedScript = '
        <script id="filament-embed-script">
            (function() {
                // Wait for DOM to be ready
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", hideElements);
                } else {
                    hideElements();
                }
                
                function hideElements() {
                    // Hide sidebar
                    const sidebarSelectors = [
                        ".fi-sidebar",
                        "[data-sidebar]",
                        "aside[class*=\'sidebar\']",
                        "nav[class*=\'sidebar\']"
                    ];
                    
                    sidebarSelectors.forEach(selector => {
                        const elements = document.querySelectorAll(selector);
                        elements.forEach(el => {
                            el.style.display = "none";
                            el.remove();
                        });
                    });
                    
                    // Hide topbar/header
                    const topbarSelectors = [
                        ".fi-topbar",
                        "[data-topbar]",
                        "header[class*=\'topbar\']",
                        "header[class*=\'header\']"
                    ];
                    
                    topbarSelectors.forEach(selector => {
                        const elements = document.querySelectorAll(selector);
                        elements.forEach(el => {
                            el.style.display = "none";
                            el.remove();
                        });
                    });
                    
                    // Adjust main content
                    const mainSelectors = [
                        ".fi-main",
                        "[data-main]",
                        "main[class*=\'main\']"
                    ];
                    
                    mainSelectors.forEach(selector => {
                        const elements = document.querySelectorAll(selector);
                        elements.forEach(el => {
                            el.style.marginLeft = "0";
                            el.style.paddingLeft = "0";
                            el.style.width = "100%";
                            el.style.maxWidth = "100%";
                        });
                    });
                    
                    // Hide account/user menu
                    const userMenuSelectors = [
                        ".fi-account-widget",
                        ".fi-user-menu",
                        "[data-user-menu]"
                    ];
                    
                    userMenuSelectors.forEach(selector => {
                        const elements = document.querySelectorAll(selector);
                        elements.forEach(el => {
                            el.style.display = "none";
                            el.remove();
                        });
                    });
                    
                    // Hide breadcrumbs
                    const breadcrumbSelectors = [
                        ".fi-breadcrumbs",
                        "[data-breadcrumbs]",
                        "nav[aria-label=\'breadcrumb\']"
                    ];
                    
                    breadcrumbSelectors.forEach(selector => {
                        const elements = document.querySelectorAll(selector);
                        elements.forEach(el => {
                            el.style.display = "none";
                            el.remove();
                        });
                    });
                }
                
                // Also run after a short delay to catch dynamically loaded elements
                setTimeout(hideElements, 500);
                setTimeout(hideElements, 1000);
            })();
        </script>
        ';
        
        // Inject styles and script before closing head tag, or at the beginning of body
        if (strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', $embedStyles . '</head>', $content);
        } else {
            $content = $embedStyles . $content;
        }
        
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $embedScript . '</body>', $content);
        } else {
            $content = $content . $embedScript;
        }
        
        $response->setContent($content);
        
        return $response;
    }
}

