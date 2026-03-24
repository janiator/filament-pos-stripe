<?php

namespace App\Enums;

enum PowerOfficeIntegrationStatus: string
{
    case NotConnected = 'not_connected';
    case PendingOnboarding = 'pending_onboarding';
    case Connected = 'connected';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotConnected => 'Not connected',
            self::PendingOnboarding => 'Pending onboarding',
            self::Connected => 'Connected',
            self::Error => 'Error',
        };
    }
}
