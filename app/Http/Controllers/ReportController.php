<?php

namespace App\Http\Controllers;

use App\Models\PosSession;
use App\Models\Store;
use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Filament\Resources\PosReports\Pages\PosReports;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Generate and download X-report as PDF
     */
    public function downloadXReportPdf(Request $request, string $tenant, int $sessionId)
    {
        $store = Store::where('slug', $tenant)->firstOrFail();
        
        // Verify user has access to this store
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this store');
        }
        
        $session = PosSession::where('id', $sessionId)
            ->where('store_id', $store->id)
            ->where('status', 'open')
            ->firstOrFail();

        // Generate report data
        $report = PosSessionsTable::generateXReport($session);

        // Log X-report event (13008) per § 2-8-2
        // Include complete report data in event_data for electronic journal compliance
        \App\Models\PosEvent::create([
            'store_id' => $session->store_id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => Auth::id(),
            'event_code' => \App\Models\PosEvent::EVENT_X_REPORT,
            'event_type' => 'report',
            'description' => "X-report PDF generated for session {$session->session_number}",
            'event_data' => [
                'report_type' => 'X-Report',
                'session_number' => $session->session_number,
                'report_data' => $report, // Complete report data for electronic journal
            ],
            'occurred_at' => now(),
        ]);

        // Generate PDF using the same template as the preview
        $html = view('reports.embed.x-report', [
            'session' => $session,
            'report' => $report,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "X-Rapport-{$session->session_number}-" . now()->format('Y-m-d-H-i-s') . '.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Generate and download Z-report as PDF
     */
    public function downloadZReportPdf(Request $request, string $tenant, int $sessionId)
    {
        $store = Store::where('slug', $tenant)->firstOrFail();
        
        // Verify user has access to this store
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this store');
        }
        
        $session = PosSession::where('id', $sessionId)
            ->where('store_id', $store->id)
            ->where('status', 'closed')
            ->firstOrFail();

        // Generate report data
        $report = PosSessionsTable::generateZReport($session);

        // Log Z-report event (13009) per § 2-8-3
        // Include complete report data in event_data for electronic journal compliance
        \App\Models\PosEvent::create([
            'store_id' => $session->store_id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => Auth::id(),
            'event_code' => \App\Models\PosEvent::EVENT_Z_REPORT,
            'event_type' => 'report',
            'description' => "Z-report PDF generated for session {$session->session_number}",
            'event_data' => [
                'report_type' => 'Z-Report',
                'session_number' => $session->session_number,
                'report_data' => $report, // Complete report data for electronic journal
            ],
            'occurred_at' => now(),
        ]);

        // Generate PDF using the same template as the preview
        $html = view('reports.embed.z-report', [
            'session' => $session,
            'report' => $report,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "Z-Rapport-{$session->session_number}-" . now()->format('Y-m-d-H-i-s') . '.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Embed X-report view (for Filament frontend)
     */
    public function embedXReport(Request $request, string $tenant, int $sessionId)
    {
        $store = Store::where('slug', $tenant)->firstOrFail();
        // Verify user has access to this store
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this store');
        }
        $session = PosSession::where('id', $sessionId)
            ->where('store_id', $store->id)
            ->where('status', 'open')
            ->firstOrFail();

        // Generate report data
        $report = PosSessionsTable::generateXReport($session);

        return view('reports.embed.x-report', [
            'session' => $session,
            'report' => $report,
        ]);
    }

    /**
     * Embed Z-report view (for Filament frontend)
     */
    public function embedZReport(Request $request, string $tenant, int $sessionId)
    {
        $store = Store::where('slug', $tenant)->firstOrFail();
        
        // Verify user has access to this store
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this store');
        }
        
        $session = PosSession::where('id', $sessionId)
            ->where('store_id', $store->id)
            ->where('status', 'closed')
            ->firstOrFail();

        // Generate report data
        $report = PosSessionsTable::generateZReport($session);

        return view('reports.embed.z-report', [
            'session' => $session,
            'report' => $report,
        ]);
    }

    /**
     * Generate and download X-report as PDF (API endpoint with Bearer token auth)
     */
    public function downloadXReportPdfApi(Request $request, int $sessionId)
    {
        $user = $request->user();
        
        // Get store from query parameter, header, or user's current store
        $storeSlug = $request->query('store') ?? $request->header('X-Store-Slug');
        $store = null;
        
        if ($storeSlug) {
            $store = Store::where('slug', $storeSlug)->first();
        } else {
            $store = $user->currentStore();
        }
        
        if (!$store) {
            abort(403, 'Store not found or not selected');
        }
        
        // Verify user has access to this store
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this store');
        }
        
        $session = PosSession::where('id', $sessionId)
            ->where('store_id', $store->id)
            ->where('status', 'open')
            ->firstOrFail();

        // Generate report data
        $report = PosSessionsTable::generateXReport($session);

        // Log X-report event (13008) per § 2-8-2
        // Include complete report data in event_data for electronic journal compliance
        \App\Models\PosEvent::create([
            'store_id' => $session->store_id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $user->id,
            'event_code' => \App\Models\PosEvent::EVENT_X_REPORT,
            'event_type' => 'report',
            'description' => "X-report PDF generated for session {$session->session_number}",
            'event_data' => [
                'report_type' => 'X-Report',
                'session_number' => $session->session_number,
                'report_data' => $report, // Complete report data for electronic journal
            ],
            'occurred_at' => now(),
        ]);

        // Generate PDF using the same template as the preview
        $html = view('reports.embed.x-report', [
            'session' => $session,
            'report' => $report,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "X-Rapport-{$session->session_number}-" . now()->format('Y-m-d-H-i-s') . '.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Generate and download Z-report as PDF (API endpoint with Bearer token auth)
     */
    public function downloadZReportPdfApi(Request $request, int $sessionId)
    {
        $user = $request->user();
        
        // Get store from query parameter, header, or user's current store
        $storeSlug = $request->query('store') ?? $request->header('X-Store-Slug');
        $store = null;
        
        if ($storeSlug) {
            $store = Store::where('slug', $storeSlug)->first();
        } else {
            $store = $user->currentStore();
        }
        
        if (!$store) {
            abort(403, 'Store not found or not selected');
        }
        
        // Verify user has access to this store
        $isSuperAdmin = $user->hasRole('super_admin');
        $hasStoreAccess = $user->stores()->where('stores.id', $store->id)->exists();
        if (!$isSuperAdmin && !$hasStoreAccess) {
            abort(403, 'You do not have access to this store');
        }
        
        $session = PosSession::where('id', $sessionId)
            ->where('store_id', $store->id)
            ->where('status', 'closed')
            ->firstOrFail();

        // Generate report data
        $report = PosSessionsTable::generateZReport($session);

        // Log Z-report event (13009) per § 2-8-3
        // Include complete report data in event_data for electronic journal compliance
        \App\Models\PosEvent::create([
            'store_id' => $session->store_id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $user->id,
            'event_code' => \App\Models\PosEvent::EVENT_Z_REPORT,
            'event_type' => 'report',
            'description' => "Z-report PDF generated for session {$session->session_number}",
            'event_data' => [
                'report_type' => 'Z-Report',
                'session_number' => $session->session_number,
                'report_data' => $report, // Complete report data for electronic journal
            ],
            'occurred_at' => now(),
        ]);

        // Generate PDF using the same template as the preview
        $html = view('reports.embed.z-report', [
            'session' => $session,
            'report' => $report,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "Z-Rapport-{$session->session_number}-" . now()->format('Y-m-d-H-i-s') . '.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
