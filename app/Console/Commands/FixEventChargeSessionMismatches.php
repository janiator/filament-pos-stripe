<?php

namespace App\Console\Commands;

use App\Models\PosEvent;
use App\Models\ConnectedCharge;
use App\Models\PosSession;
use Illuminate\Console\Command;

class FixEventChargeSessionMismatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:fix-event-charge-session-mismatches
                            {--store-id= : Filter by specific store ID}
                            {--dry-run : Show what would be updated without making changes}
                            {--use-charge-session : Use charge\'s pos_session_id as source of truth (default)}
                            {--use-event-session : Use event\'s pos_session_id as source of truth}
                            {--limit= : Limit the number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix mismatches between event pos_session_id and charge pos_session_id for events with related_charge_id';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $storeId = $this->option('store-id');
        $useChargeSession = !$this->option('use-event-session'); // Default to using charge session
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info('Fixing event-charge session ID mismatches...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        $this->info('Strategy: ' . ($useChargeSession ? 'Use charge\'s pos_session_id as source of truth' : 'Use event\'s pos_session_id as source of truth'));
        $this->newLine();

        // Find events with related_charge_id that have mismatched session IDs
        $query = PosEvent::whereNotNull('related_charge_id')
            ->whereNotNull('pos_session_id')
            ->with('relatedCharge');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $events = $query->get();
        $totalEvents = $events->count();

        if ($totalEvents === 0) {
            $this->info('No events found to check.');
            return Command::SUCCESS;
        }

        $this->info("Checking {$totalEvents} events with related_charge_id...");

        $stats = [
            'processed' => 0,
            'matched' => 0,
            'mismatched' => 0,
            'fixed_events' => 0,
            'fixed_charges' => 0,
            'skipped_no_charge' => 0,
            'skipped_no_session' => 0,
            'skipped_both_null' => 0,
            'errors' => 0,
        ];

        $bar = $this->output->createProgressBar($totalEvents);
        $bar->start();

        foreach ($events as $event) {
            $stats['processed']++;

            try {
                $charge = $event->relatedCharge;

                if (!$charge) {
                    $stats['skipped_no_charge']++;
                    $bar->advance();
                    continue;
                }

                $eventSessionId = $event->pos_session_id;
                $chargeSessionId = $charge->pos_session_id;

                // Skip if both are null (handled by other recovery commands)
                if (!$eventSessionId && !$chargeSessionId) {
                    $stats['skipped_both_null']++;
                    $bar->advance();
                    continue;
                }

                // Skip if one is null (handled by other recovery commands)
                if (!$eventSessionId || !$chargeSessionId) {
                    $stats['skipped_no_session']++;
                    $bar->advance();
                    continue;
                }

                // Check if they match
                if ($eventSessionId === $chargeSessionId) {
                    $stats['matched']++;
                    $bar->advance();
                    continue;
                }

                // They don't match - determine which is correct
                $stats['mismatched']++;

                // Try to determine correct session by checking session_number in metadata
                $correctSessionId = null;
                $source = null;

                // Check charge metadata for session_number
                $chargeSessionNumber = $charge->metadata['pos_session_number'] ?? null;
                if ($chargeSessionNumber) {
                    $sessionByNumber = PosSession::where('session_number', $chargeSessionNumber)
                        ->where('store_id', $event->store_id)
                        ->first();
                    if ($sessionByNumber) {
                        $correctSessionId = $sessionByNumber->id;
                        $source = 'charge metadata (session_number)';
                    }
                }

                // If not found, check event_data for session_number
                if (!$correctSessionId) {
                    $eventSessionNumber = $event->event_data['session_number'] ?? null;
                    if ($eventSessionNumber) {
                        $sessionByNumber = PosSession::where('session_number', $eventSessionNumber)
                            ->where('store_id', $event->store_id)
                            ->first();
                        if ($sessionByNumber) {
                            $correctSessionId = $sessionByNumber->id;
                            $source = 'event data (session_number)';
                        }
                    }
                }

                // If still not found, use the strategy option
                if (!$correctSessionId) {
                    if ($useChargeSession) {
                        $correctSessionId = $chargeSessionId;
                        $source = 'charge pos_session_id (strategy)';
                    } else {
                        $correctSessionId = $eventSessionId;
                        $source = 'event pos_session_id (strategy)';
                    }
                }

                $session = PosSession::find($correctSessionId);
                
                if (!$session) {
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->warn("  ⚠ Event {$event->id}: Session {$correctSessionId} not found");
                    }
                    $stats['errors']++;
                    $bar->advance();
                    continue;
                }

                // Determine what needs to be updated
                $needsEventUpdate = $event->pos_session_id !== $correctSessionId;
                $needsChargeUpdate = $charge->pos_session_id !== $correctSessionId;

                if ($needsEventUpdate) {
                    if (!$dryRun) {
                        $event->pos_session_id = $correctSessionId;
                        $event->pos_device_id = $session->pos_device_id;
                        $event->saveQuietly();
                    }
                    $stats['fixed_events']++;
                }

                if ($needsChargeUpdate) {
                    if (!$dryRun) {
                        $charge->pos_session_id = $correctSessionId;
                        $charge->saveQuietly();
                    }
                    $stats['fixed_charges']++;
                }

                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $updates = [];
                    if ($needsEventUpdate) {
                        $updates[] = "Event {$event->id}: {$eventSessionId} → {$correctSessionId}";
                    }
                    if ($needsChargeUpdate) {
                        $updates[] = "Charge {$charge->id}: {$chargeSessionId} → {$correctSessionId}";
                    }
                    if (!empty($updates)) {
                        $this->info("  ✓ " . implode(', ', $updates) . " (source: {$source})");
                    }
                }

            } catch (\Exception $e) {
                $stats['errors']++;
                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->error("  ✗ Event {$event->id}: Error - {$e->getMessage()}");
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('Fix Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Events Processed', $stats['processed']],
                ['Already Matched', $stats['matched']],
                ['Mismatches Found', $stats['mismatched']],
                ['Events Fixed', $stats['fixed_events']],
                ['Charges Fixed', $stats['fixed_charges']],
                ['Skipped (No Charge)', $stats['skipped_no_charge']],
                ['Skipped (No Session)', $stats['skipped_no_session']],
                ['Skipped (Both Null)', $stats['skipped_both_null']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($dryRun && ($stats['fixed_events'] > 0 || $stats['fixed_charges'] > 0)) {
            $this->warn("\nRun without --dry-run to apply these changes.");
        }

        return Command::SUCCESS;
    }
}
