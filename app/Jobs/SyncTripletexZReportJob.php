<?php

namespace App\Jobs;

use App\Services\Tripletex\TripletexZReportSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncTripletexZReportJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $posSessionId,
        public bool $force = false,
    ) {}

    public function handle(TripletexZReportSync $sync): void
    {
        $sync->sync($this->posSessionId, $this->force);
    }
}
