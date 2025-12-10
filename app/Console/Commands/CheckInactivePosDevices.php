<?php

namespace App\Console\Commands;

use App\Models\PosDevice;
use App\Models\PosEvent;
use App\Models\PosSession;
use Illuminate\Console\Command;

class CheckInactivePosDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:check-inactive-devices 
                            {--timeout=15 : Minutes of inactivity before considering device stopped}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for POS devices that haven\'t sent heartbeats and create stop events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeoutMinutes = (int) $this->option('timeout');
        $inactivityThreshold = now()->subMinutes($timeoutMinutes);

        $this->info("Checking for devices inactive for more than {$timeoutMinutes} minutes...");

        // Find devices that:
        // 1. Have a last_seen_at timestamp (were active at some point)
        // 2. Haven't sent a heartbeat in the timeout period
        // 3. Are currently marked as active (not already marked as offline)
        // 4. Don't have a recent stop event (within last 5 minutes) to avoid duplicates
        $inactiveDevices = PosDevice::whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $inactivityThreshold)
            ->whereIn('device_status', ['active', 'inactive'])
            ->get()
            ->filter(function ($device) {
                // Check if there's a recent stop event (within last 5 minutes)
                $recentStopEvent = PosEvent::where('pos_device_id', $device->id)
                    ->where('event_code', PosEvent::EVENT_APPLICATION_SHUTDOWN)
                    ->where('occurred_at', '>=', now()->subMinutes(5))
                    ->exists();

                return !$recentStopEvent;
            });

        if ($inactiveDevices->isEmpty()) {
            $this->info('No inactive devices found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$inactiveDevices->count()} inactive device(s).");

        $stopEventsCreated = 0;

        foreach ($inactiveDevices as $device) {
            // Get current session if exists
            $currentSession = PosSession::where('store_id', $device->store_id)
                ->where('pos_device_id', $device->id)
                ->where('status', 'open')
                ->first();

            // Update device status to offline
            $device->update([
                'device_status' => 'offline',
            ]);

            // Calculate inactivity duration
            $inactivityDuration = $device->last_seen_at 
                ? round($device->last_seen_at->diffInMinutes(now()), 2)
                : null;

            // Log application shutdown event (13002)
            PosEvent::create([
                'store_id' => $device->store_id,
                'pos_device_id' => $device->id,
                'pos_session_id' => $currentSession?->id,
                'user_id' => null, // No user context available for auto-detected stop
                'event_code' => PosEvent::EVENT_APPLICATION_SHUTDOWN,
                'event_type' => 'application',
                'description' => "POS application stopped on device {$device->device_name} (no heartbeat received)",
                'event_data' => [
                    'device_name' => $device->device_name,
                    'platform' => $device->platform,
                    'has_open_session' => !is_null($currentSession),
                    'session_id' => $currentSession?->id,
                    'inactivity_duration_minutes' => $inactivityDuration,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'auto_detected' => true,
                ],
                'occurred_at' => now(),
            ]);

            $stopEventsCreated++;
            $this->line("  - Created stop event for device: {$device->device_name} (ID: {$device->id})");
        }

        $this->info("Successfully created {$stopEventsCreated} stop event(s).");

        return Command::SUCCESS;
    }
}
