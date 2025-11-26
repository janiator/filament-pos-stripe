<?php

namespace App\Actions\PosSessions;

use App\Models\PosSession;
use App\Models\PosDevice;

class GetCurrentSessionForDevice
{
    /**
     * Get the current open session for a POS device
     */
    public function __invoke(int $posDeviceId): ?PosSession
    {
        return PosSession::where('pos_device_id', $posDeviceId)
            ->where('status', 'open')
            ->first();
    }
}

