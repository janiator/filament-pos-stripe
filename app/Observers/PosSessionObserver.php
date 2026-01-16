<?php

namespace App\Observers;

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Mail\ZReportMail;
use App\Models\PosEvent;
use App\Models\PosSession;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class PosSessionObserver
{
    /**
     * Handle the PosSession "created" event.
     */
    public function created(PosSession $session): void
    {
        // Log session opened event (13020)
        PosEvent::create([
            'store_id' => $session->store_id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $session->user_id,
            'event_code' => PosEvent::EVENT_SESSION_OPENED,
            'event_type' => 'session',
            'description' => "Session {$session->session_number} opened",
            'event_data' => [
                'session_number' => $session->session_number,
                'opening_balance' => $session->opening_balance,
            ],
            'occurred_at' => $session->opened_at,
        ]);
    }

    /**
     * Handle the PosSession "updated" event.
     */
    public function updated(PosSession $session): void
    {
        // Log session closed event (13021) when status changes to closed
        if ($session->wasChanged('status') && $session->status === 'closed') {
            PosEvent::create([
                'store_id' => $session->store_id,
                'pos_device_id' => $session->pos_device_id,
                'pos_session_id' => $session->id,
                'user_id' => $session->user_id,
                'event_code' => PosEvent::EVENT_SESSION_CLOSED,
                'event_type' => 'session',
                'description' => "Session {$session->session_number} closed",
                'event_data' => [
                    'session_number' => $session->session_number,
                    'expected_cash' => $session->expected_cash,
                    'actual_cash' => $session->actual_cash,
                    'cash_difference' => $session->cash_difference,
                ],
                'occurred_at' => $session->closed_at ?? now(),
            ]);

            // Send Z-report email if store has z_report_email configured
            $this->sendZReportEmail($session);
        }
    }

    /**
     * Send Z-report email with PDF attachment
     */
    protected function sendZReportEmail(PosSession $session): void
    {
        try {
            // Load store relationship
            $session->load('store');
            $store = $session->store;

            // Check if store has z_report_email configured
            if (!$store || !$store->z_report_email) {
                return;
            }

            // Load all necessary relationships
            $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);

            // Generate Z-report data
            $report = PosSessionsTable::generateZReport($session);

            // Generate PDF
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

            $pdfContent = $dompdf->output();
            $filename = "Z-Rapport-{$session->session_number}-" . now()->format('Y-m-d-H-i-s') . '.pdf';

            // Send email
            Mail::to($store->z_report_email)->send(
                new ZReportMail($session, $pdfContent, $filename)
            );

            Log::info("Z-report email sent to {$store->z_report_email} for session {$session->session_number}");
        } catch (\Exception $e) {
            // Log error but don't fail the session closing
            Log::error("Failed to send Z-report email for session {$session->session_number}: " . $e->getMessage());
        }
    }
}
