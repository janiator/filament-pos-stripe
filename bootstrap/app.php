<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies for Herd's reverse proxy setup
        $middleware->trustProxies(at: '*');
        
        // Set dynamic APP_URL based on request (must be before CORS)
        $middleware->append(\App\Http\Middleware\SetDynamicAppUrl::class);
        
        // Clean API query parameters (remove null/empty values)
        $middleware->append(\App\Http\Middleware\CleanApiQueryParameters::class);
        
        // Add CORS headers for storage routes
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        
        // Configure authentication middleware to prevent redirects for API routes
        // For API routes, we want JSON responses, not redirects
        $middleware->redirectGuestsTo(function ($request) {
            // For API routes, return null to prevent redirect attempts
            // The exception handler will catch AuthenticationException and return JSON
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            // For web routes, return null (no login route defined)
            // If you add web authentication later, uncomment and set your login route:
            // return route('login');
            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Ensure API routes return JSON for validation errors instead of redirects
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
        
        // Ensure API routes return JSON for 404 errors
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });
        
        // Ensure API routes return JSON for model not found errors
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });
        
        // Ensure API routes return JSON for 401 (Unauthorized) errors
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Unauthenticated.',
                ], 401);
            }
        });
        
        // Ensure API routes return JSON for 403 (Forbidden) errors
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Access denied.',
                ], 403);
            }
        });
        
        // Catch-all: Ensure all API errors for API routes - ensure JSON responses
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                
                // Don't expose sensitive error details in production
                $message = config('app.debug') 
                    ? $e->getMessage() 
                    : 'An error occurred while processing your request.';
                
                $response = [
                    'message' => $message,
                ];
                
                // Include stack trace in debug mode
                if (config('app.debug')) {
                    $response['exception'] = get_class($e);
                    $response['file'] = $e->getFile();
                    $response['line'] = $e->getLine();
                    $response['trace'] = $e->getTraceAsString();
                }
                
                return response()->json($response, $statusCode);
            }
        });
    })->create();
