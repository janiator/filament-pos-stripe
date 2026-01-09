<?php

namespace App\Console\Commands;

use App\Filament\Resources\PosSessions\Tables\PosSessionsTable;
use App\Models\PosSession;
use Illuminate\Console\Command;

class BackfillProductsSoldInZReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'z-reports:backfill-products-sold 
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit the number of sessions to process}
                            {--session-id= : Process a specific session ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill products_sold and sales_by_vendor data in Z-reports that were generated before these features were added';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $sessionId = $this->option('session-id') ? (int) $this->option('session-id') : null;

        $this->info('Analyzing Z-reports for missing products_sold and sales_by_vendor data...');
        $this->newLine();

        // Build query - find sessions missing either products_sold or sales_by_vendor
        // We'll filter in PHP to avoid JSON parsing errors with corrupted data
        $query = PosSession::where('status', 'closed')
            ->whereNotNull('closing_data')
            ->orderBy('closed_at', 'desc');

        if ($sessionId) {
            $query->where('id', $sessionId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $allSessions = $query->get();
        
        // Initialize stats array early to track all metrics
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'products_sold_added' => 0,
            'sales_by_vendor_added' => 0,
            'skipped_no_receipts' => 0,
            'skipped_no_items' => 0,
            'skipped_no_z_report_data' => 0,
            'skipped_corrupted_json' => 0,
            'errors' => 0,
        ];
        
        // Filter in PHP to handle potentially corrupted JSON data
        $sessions = collect();
        
        foreach ($allSessions as $session) {
            try {
                $closingData = $session->closing_data;
                if (!$closingData || !is_array($closingData)) {
                    continue;
                }
                
                $zReportData = $closingData['z_report_data'] ?? null;
                // Skip sessions without z_report_data - they need full report generation, not backfill
                if (!$zReportData || !is_array($zReportData)) {
                    $stats['skipped_no_z_report_data']++;
                    if ($dryRun || $this->getOutput()->isVerbose()) {
                        $this->line("  ⚠ Session {$session->session_number} (ID: {$session->id}): No z_report_data - skipping (requires full regeneration)");
                    }
                    continue;
                }
                
                // Check if products_sold is missing or empty
                $hasProductsSold = isset($zReportData['products_sold']) && 
                                   is_array($zReportData['products_sold']) && 
                                   !empty($zReportData['products_sold']);
                
                // Check if sales_by_vendor is missing or empty
                $hasSalesByVendor = isset($zReportData['sales_by_vendor']) && 
                                   is_array($zReportData['sales_by_vendor']) && 
                                   !empty($zReportData['sales_by_vendor']);
                
                // Add to list if either is missing
                if (!$hasProductsSold || !$hasSalesByVendor) {
                    $sessions->push($session);
                }
            } catch (\Exception $e) {
                // Skip sessions with corrupted JSON data
                $stats['skipped_corrupted_json']++;
                if ($dryRun || $this->getOutput()->isVerbose()) {
                    $this->warn("  ⚠ Session {$session->session_number} (ID: {$session->id}): Corrupted JSON data - skipping");
                }
            }
        }

        if ($sessions->isEmpty()) {
            $this->info('No sessions found that need products_sold or sales_by_vendor backfill.');
            return Command::SUCCESS;
        }

        $this->info("Found {$sessions->count()} session(s) that may need products_sold or sales_by_vendor backfill.");
        $this->newLine();

        foreach ($sessions as $session) {
            $stats['processed']++;

            // Check if session has sales receipts
            $salesReceipts = $session->receipts()->where('receipt_type', 'sales')->get();
            
            if ($salesReceipts->isEmpty()) {
                $this->line("  Session {$session->session_number} (ID: {$session->id}): No sales receipts - skipping");
                $stats['skipped_no_receipts']++;
                continue;
            }

            // Check if receipts have items
            $hasItems = false;
            foreach ($salesReceipts as $receipt) {
                $items = $receipt->receipt_data['items'] ?? [];
                if (!empty($items)) {
                    $hasItems = true;
                    break;
                }
            }

            if (!$hasItems) {
                $this->line("  Session {$session->session_number} (ID: {$session->id}): Receipts have no items - skipping");
                $stats['skipped_no_items']++;
                continue;
            }

            // Check what needs to be backfilled
            $cachedReport = $session->closing_data['z_report_data'] ?? [];
            $needsProductsSold = !isset($cachedReport['products_sold']) || empty($cachedReport['products_sold']);
            $needsSalesByVendor = !isset($cachedReport['sales_by_vendor']) || empty($cachedReport['sales_by_vendor']);

            if (!$needsProductsSold && !$needsSalesByVendor) {
                $this->line("  Session {$session->session_number} (ID: {$session->id}): Already has both products_sold and sales_by_vendor - skipping");
                continue;
            }

            // Calculate missing data
            try {
                $productsSold = null;
                $salesByVendor = null;
                $updates = [];

                if ($needsProductsSold) {
                    $productsSold = PosSessionsTable::calculateProductsSold($session);
                    if ($productsSold->isNotEmpty()) {
                        $updates[] = "{$productsSold->count()} products";
                    }
                }

                if ($needsSalesByVendor) {
                    $salesByVendor = PosSessionsTable::calculateSalesByVendor($session);
                    if ($salesByVendor->isNotEmpty()) {
                        $updates[] = "{$salesByVendor->count()} vendors";
                    }
                }

                if (empty($updates)) {
                    $this->line("  Session {$session->session_number} (ID: {$session->id}): Calculated 0 products/vendors - skipping");
                    $stats['skipped_no_items']++;
                    continue;
                }

                if ($dryRun) {
                    $this->info("  [DRY RUN] Session {$session->session_number} (ID: {$session->id}): Would add " . implode(', ', $updates));
                } else {
                    // Update closing_data with backfilled data
                    // Note: We only reach here if z_report_data already exists (checked earlier)
                    $closingData = $session->closing_data ?? [];
                    
                    // Ensure z_report_data exists (should always be true at this point, but safety check)
                    if (!isset($closingData['z_report_data']) || !is_array($closingData['z_report_data'])) {
                        $this->warn("  ⚠ Session {$session->session_number} (ID: {$session->id}): z_report_data missing - skipping (requires full regeneration)");
                        $stats['skipped_no_z_report_data']++;
                        continue;
                    }
                    
                    if ($needsProductsSold && $productsSold && $productsSold->isNotEmpty()) {
                        $closingData['z_report_data']['products_sold'] = $productsSold->toArray();
                        $stats['products_sold_added']++;
                    }
                    
                    if ($needsSalesByVendor && $salesByVendor && $salesByVendor->isNotEmpty()) {
                        $closingData['z_report_data']['sales_by_vendor'] = $salesByVendor->toArray();
                        $stats['sales_by_vendor_added']++;
                    }
                    
                    $closingData['z_report_data_backfilled_at'] = now()->toISOString();
                    
                    $session->closing_data = $closingData;
                    $session->saveQuietly();
                    
                    $this->info("  ✓ Session {$session->session_number} (ID: {$session->id}): Added " . implode(', ', $updates));
                    $stats['updated']++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Session {$session->session_number} (ID: {$session->id}): Error - {$e->getMessage()}");
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line("Processed: {$stats['processed']}");
        $this->line("Updated: {$stats['updated']}");
        $this->line("Products sold added: {$stats['products_sold_added']}");
        $this->line("Sales by vendor added: {$stats['sales_by_vendor_added']}");
        $this->line("Skipped (no receipts): {$stats['skipped_no_receipts']}");
        $this->line("Skipped (no items): {$stats['skipped_no_items']}");
        if ($stats['skipped_no_z_report_data'] > 0) {
            $this->warn("Skipped (no z_report_data - requires full regeneration): {$stats['skipped_no_z_report_data']}");
        }
        if ($stats['skipped_corrupted_json'] > 0) {
            $this->warn("Skipped (corrupted JSON): {$stats['skipped_corrupted_json']}");
        }
        $this->line("Errors: {$stats['errors']}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Use without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }
}
