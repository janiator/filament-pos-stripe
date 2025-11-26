<?php

namespace App\Observers;

use App\Models\PosEvent;
use App\Models\PosSession;

class PosSessionObserver
{
    /**
     * Handle the PosSession "created" event.
     */
    public function created(PosSession $session): void
    {
        // Log session opened event (13020)
        PosEvent::create([
            'store_id' => $session->store_id,
            'pos_device_id' => $session->pos_device_id,
            'pos_session_id' => $session->id,
            'user_id' => $session->user_id,
            'event_code' => PosEvent::EVENT_SESSION_OPENED,
            'event_type' => 'session',
            'description' => "Session {$session->session_number} opened",
            'event_data' => [
                'session_number' => $session->session_number,
                'opening_balance' => $session->opening_balance,
            ],
            'occurred_at' => $session->opened_at,
        ]);
    }

    /**
     * Handle the PosSession "updated" event.
     */
    public function updated(PosSession $session): void
    {
        // Log session closed event (13021) when status changes to closed
        if ($session->wasChanged('status') && $session->status === 'closed') {
            PosEvent::create([
                'store_id' => $session->store_id,
                'pos_device_id' => $session->pos_device_id,
                'pos_session_id' => $session->id,
                'user_id' => $session->user_id,
                'event_code' => PosEvent::EVENT_SESSION_CLOSED,
                'event_type' => 'session',
                'description' => "Session {$session->session_number} closed",
                'event_data' => [
                    'session_number' => $session->session_number,
                    'expected_cash' => $session->expected_cash,
                    'actual_cash' => $session->actual_cash,
                    'cash_difference' => $session->cash_difference,
                ],
                'occurred_at' => $session->closed_at ?? now(),
            ]);
        }
    }
}
