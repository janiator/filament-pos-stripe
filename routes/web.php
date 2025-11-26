<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Receipt XML download route (works with Filament tenant routing)
Route::middleware(['auth', 'web'])->group(function () {
    Route::get('/app/store/{tenant}/receipts/{id}/xml', function ($tenant, $id) {
        $receipt = \App\Models\Receipt::findOrFail($id);
        
        // Verify receipt belongs to the tenant store
        $store = \App\Models\Store::where('slug', $tenant)->firstOrFail();
        if ($receipt->store_id !== $store->id) {
            abort(403, 'Receipt does not belong to this store');
        }
        
        $xml = app(\App\Services\ReceiptTemplateService::class)->renderReceipt($receipt);
        
        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"receipt-{$receipt->receipt_number}.xml\"",
        ]);
    })->name('receipts.xml');
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
