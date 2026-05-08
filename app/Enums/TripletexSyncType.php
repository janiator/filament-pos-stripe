<?php

namespace App\Enums;

enum TripletexSyncType: string
{
    case ZReport = 'z_report';
    case Payout = 'payout';

    public function label(): string
    {
        return match ($this) {
            self::ZReport => 'Z-report',
            self::Payout => 'Stripe payout',
        };
    }
}
