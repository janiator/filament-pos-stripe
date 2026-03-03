<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;

Route::get('/', function () {
    return redirect('/app/login');
});

// Stripe webhook endpoint (also available without /api prefix for compatibility)
// Exclude from CSRF protection since webhooks don't have CSRF tokens
Route::post('/connectWebhook', StripeConnectWebhookController::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->name('stripeConnect.webhook');

// Filament token-based authentication route
// This allows FlutterFlow apps to authenticate users via API token and access Filament panel
// Usage: /filament-auth/{token}?store={store_slug}&redirect={path}
Route::get('/filament-auth/{token}', [\App\Http\Controllers\FilamentAuthController::class, 'authenticate'])
    ->middleware('web')
    ->name('filament.auth.token');

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
    
    // Receipt preview route (embedded in Filament)
    Route::get('/receipts/{id}/preview', function ($id) {
        $receipt = \App\Models\Receipt::with(['store', 'charge', 'posSession', 'user', 'originalReceipt'])
            ->findOrFail($id);
        
        // Verify user has access to this receipt's store
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        
        // Check if user can access the store (super admin or store member)
        $store = $receipt->store;
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this receipt');
        }
        
        // Redirect to the preview-only page with receipt ID
        return redirect('/epson-editor/preview-only.html?receipt_id=' . $receipt->id);
    })->name('receipts.preview');
    
    // Simple receipt XML route for preview (no tenant in URL)
    Route::get('/receipts/{id}/xml', function ($id) {
        $receipt = \App\Models\Receipt::with(['store', 'charge', 'posSession', 'user', 'originalReceipt'])
            ->findOrFail($id);
        
        // Verify user has access to this receipt's store
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        
        // Check if user can access the store (super admin or store member)
        $store = $receipt->store;
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this receipt');
        }
        
        $xml = app(\App\Services\ReceiptTemplateService::class)->renderReceipt($receipt);
        
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="receipt-' . $receipt->receipt_number . '.xml"',
        ]);
    })->name('receipts.xml.simple');
    
    // Report PDF download routes (works with Filament tenant routing)
    Route::get('/app/store/{tenant}/pos-sessions/{sessionId}/x-report/pdf', [\App\Http\Controllers\ReportController::class, 'downloadXReportPdf'])
        ->name('reports.x-report.pdf');
    Route::get('/app/store/{tenant}/pos-sessions/{sessionId}/z-report/pdf', [\App\Http\Controllers\ReportController::class, 'downloadZReportPdf'])
        ->name('reports.z-report.pdf');
    
    // Report embed routes (for embedding in Filament frontend)
    Route::get('/app/store/{tenant}/pos-sessions/{sessionId}/x-report/embed', [\App\Http\Controllers\ReportController::class, 'embedXReport'])
        ->name('reports.x-report.embed');
    Route::get('/app/store/{tenant}/pos-sessions/{sessionId}/z-report/embed', [\App\Http\Controllers\ReportController::class, 'embedZReport'])
        ->name('reports.z-report.embed');
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
