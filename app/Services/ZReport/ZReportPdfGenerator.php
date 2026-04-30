<?php

namespace App\Services\ZReport;

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders the same Z-report PDF as email / Filament (Dompdf + z-report view).
 */
class ZReportPdfGenerator
{
    /**
     * @return string Raw PDF binary
     */
    public function render(PosSession $session): string
    {
        $session->load(['charges', 'posDevice', 'user', 'store', 'events', 'receipts']);

        $report = PosSessionsTable::generateZReport($session);

        $html = view('reports.embed.z-report', [
            'session' => $session,
            'report' => $report,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function suggestedFilename(PosSession $session): string
    {
        return 'Z-Rapport-'.$session->session_number.'-'.now()->format('Y-m-d-His').'.pdf';
    }
}
