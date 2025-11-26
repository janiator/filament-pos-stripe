<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Dummy login route to prevent "Route [login] not defined" errors
// This route is only used as a fallback for web authentication redirects
// API routes will be handled by the exception handler and return JSON
Route::get('/login', function () {
    // If this is an API request, return JSON instead of redirecting
    if (request()->is('api/*') || request()->expectsJson()) {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
    // For web requests, you can redirect to your actual login page
    // For now, just return a simple message
    return response('Please log in to access this page.', 401);
})->name('login');
