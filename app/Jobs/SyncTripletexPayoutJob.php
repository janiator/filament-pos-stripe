<?php

namespace App\Jobs;

use App\Services\Tripletex\TripletexPayoutSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncTripletexPayoutJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $storeStripePayoutId,
        public bool $force = false,
        public bool $skipPayoutBankTransfer = false,
    ) {}

    public function handle(TripletexPayoutSync $sync): void
    {
        $sync->sync($this->storeStripePayoutId, $this->force, $this->skipPayoutBankTransfer);
    }
}
