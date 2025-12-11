<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horizon snapshot - takes a snapshot of queue metrics every 5 minutes
// Note: This requires Redis. If using database queues, you may want to disable this.
Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();

// Pulse check - takes a single snapshot of server metrics (use --once flag)
// Note: pulse:check without --once runs continuously, so use --once for scheduled tasks
// Schedule::command('pulse:check --once')->everyMinute();

// Check for inactive POS devices and create stop events
// Runs every 5 minutes to check for devices that haven't sent heartbeats
Schedule::command('pos:check-inactive-devices')->everyFiveMinutes()->withoutOverlapping();
