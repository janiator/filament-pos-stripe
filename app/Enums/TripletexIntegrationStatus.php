<?php

namespace App\Enums;

enum TripletexIntegrationStatus: string
{
    case NotConnected = 'not_connected';
    case Connected = 'connected';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotConnected => 'Not connected',
            self::Connected => 'Connected',
            self::Error => 'Error',
        };
    }
}
