<?php

namespace App\Listeners;

use App\Actions\TerminalLocations\UpdateTerminalLocationToStripe;
use App\Models\TerminalLocation;

class SyncTerminalLocationToStripeListener
{
    /**
     * Handle the event.
     */
    public function handle(TerminalLocation $location): void
    {
        // Only sync if location has Stripe ID and relevant fields changed
        if (! $location->stripe_location_id) {
            return;
        }

        // Check if any syncable fields changed using wasChanged()
        $syncableFields = ['display_name', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'];
        $hasChanges = false;

        foreach ($syncableFields as $field) {
            if ($location->wasChanged($field)) {
                $hasChanges = true;
                break;
            }
        }

        if (! $hasChanges) {
            return;
        }

        \App\Jobs\SyncTerminalLocationToStripeJob::dispatch($location);
    }
}

