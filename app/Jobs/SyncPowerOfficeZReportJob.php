<?php

namespace App\Jobs;

use App\Services\PowerOffice\PowerOfficeZReportSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPowerOfficeZReportJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $posSessionId,
        public bool $force = false,
    ) {}

    public function handle(PowerOfficeZReportSync $sync): void
    {
        $sync->sync($this->posSessionId, $this->force);
    }
}
