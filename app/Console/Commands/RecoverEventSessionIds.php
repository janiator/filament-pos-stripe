<?php

namespace App\Console\Commands;

use App\Models\PosEvent;
use App\Models\ConnectedCharge;
use Illuminate\Console\Command;

class RecoverEventSessionIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:recover-event-session-ids
                            {--store-id= : Filter by specific store ID}
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit the number of events to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recover pos_session_id and pos_device_id for PosEvent records using related_charge_id';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $storeId = $this->option('store-id');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info('Recovering event session IDs from related charges...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find events with NULL pos_session_id but with related_charge_id
        $query = PosEvent::whereNull('pos_session_id')
            ->whereNotNull('related_charge_id');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $events = $query->get();
        $totalEvents = $events->count();

        if ($totalEvents === 0) {
            $this->info('No events found that need recovery.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalEvents} events to process.");

        $stats = [
            'processed' => 0,
            'recovered' => 0,
            'skipped_no_charge' => 0,
            'skipped_no_session' => 0,
            'errors' => 0,
        ];

        $bar = $this->output->createProgressBar($totalEvents);
        $bar->start();

        foreach ($events as $event) {
            $stats['processed']++;

            try {
                // Get the related charge with session relationship loaded
                $charge = ConnectedCharge::with('posSession')->find($event->related_charge_id);

                if (!$charge) {
                    $stats['skipped_no_charge']++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->warn("  ⚠ Event {$event->id}: Charge {$event->related_charge_id} not found");
                    }
                    $bar->advance();
                    continue;
                }

                // Check if charge has a session
                if (!$charge->pos_session_id) {
                    $stats['skipped_no_session']++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->warn("  ⚠ Event {$event->id}: Charge {$charge->id} has no pos_session_id");
                    }
                    $bar->advance();
                    continue;
                }

                // Get the session to also recover pos_device_id
                $session = $charge->posSession;
                $posDeviceId = $session?->pos_device_id;

                // Update the event
                if (!$dryRun) {
                    $event->pos_session_id = $charge->pos_session_id;
                    if ($posDeviceId) {
                        $event->pos_device_id = $posDeviceId;
                    }
                    $event->saveQuietly(); // Save without triggering observers
                }

                $stats['recovered']++;

                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->info("  ✓ Event {$event->id}: Linked to session {$charge->pos_session_id}" . 
                                ($posDeviceId ? " and device {$posDeviceId}" : ""));
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
        $this->info('Recovery Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Events Processed', $stats['processed']],
                ['Successfully Recovered', $stats['recovered']],
                ['Skipped (No Charge)', $stats['skipped_no_charge']],
                ['Skipped (No Session)', $stats['skipped_no_session']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($dryRun && $stats['recovered'] > 0) {
            $this->warn("\nRun without --dry-run to apply these changes.");
        }

        return Command::SUCCESS;
    }
}
